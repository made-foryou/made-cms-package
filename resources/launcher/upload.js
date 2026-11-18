/*
 * Made CMS: the upload field. One field with five moments (at rest, dragging, busy, done, an error per file), the
 * same in the media library, the picker and the edit shell.
 *
 * Every [data-upload] is a field of its own, also one a script puts on the page later (the side panel clones it from a
 * template). Its fixed texts are in the markup (cms/partials/upload.blade.php), the ones filled in here come as JSON
 * on the field itself (data-config), like where a file goes and the largest file. Files go to the server one at a
 * time, so it never scales three photos down at once. An error belongs to its file: the others go on, and it stays
 * until the editor leaves the file out or tries again. The field of one file (config.one: an image, a file field) takes
 * one file at a time; a field with config.kinds (an image, a list of images, a file field) refuses another kind before
 * it travels.
 *
 * What a host hears on the field (the events bubble):
 * - made-cms:upload {busy, done, total, failed, percent, sending} after every change and while a file travels, for a
 *   status line outside the field (percent of the file on its way, null when none is; sending while its bytes still
 *   travel, so it can still be stopped);
 * - made-cms:uploaded {file} for every file the library took, as the server answered it;
 * - made-cms:upload-closed {files} when the editor leaves the moment "done", with the alt texts as they are stored.
 *
 * A host stops what is still on its way with made-cms:upload-stop on the field: the file that travels and the files
 * that wait leave the field. A file the server is storing already cannot be stopped; it lands in the library.
 *
 * A host that has a button or a drop zone of its own hands its files to the field: made-cms:upload-choose opens the
 * file chooser (dispatch it in the click of the editor, which the browser asks for), and made-cms:upload-take
 * {transfer} takes the files of a drop the way a drop on the field does (a folder is no file), or {files} a list of
 * files. They go the way of any file: one at a time, each with its own error.
 *
 * A host sends the field into another folder with made-cms:upload-folder {folder: {id, name} | null} on the field
 * (null for "Alle bestanden"), also one that has not started yet. A file keeps the folder it was given with, so a file
 * that waits or is tried again never moves into a folder chosen later.
 *
 * A field with config.crop (UploadField::image($folder, crop: true)) hands the photos of a batch that can be cropped to
 * the crop step on the page ([data-media-crop], made-cms:crop) once the batch is in. The step asks their name and alt
 * text, so the field does not; it closes when the step gives them back, or asks the alt text of the photos the editor
 * did not get to there. Only a field the editor still sees hands photos over, and only to a step that takes them
 * (detail.taken); otherwise the field asks their alt text itself. An svg, an animated gif and a document never go to
 * the step.
 *
 * The field of a new version (config.replace, UploadField::replace()) sends one file at a time to the file it replaces,
 * which the host names with made-cms:upload-target {media: {id, kind, name}}: it then takes only that kind, says so, and
 * keeps that file for a row that came in before another target. It asks no alt text (the file keeps its own) and
 * closes by itself once the file is in, a photo once the crop step gave it back. made-cms:uploaded carries the notice
 * of the server ({file, notice}).
 *
 * A host that leaves the field (a dialog that closes, a choice that is used) finishes it first with
 * made-cms:upload-finish {wait} on the field: the moment "done" is left as with "Alt-tekst opslaan", a typed alt text
 * is stored and without one it is "later". wait gets a promise of true once nothing of "done" is left, and of false
 * when the server refused an alt text, which then stays with its file.
 */

const HEADERS = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

/** How long an upload runs before the field says how long it still takes, in ms. */
const ESTIMATE_AFTER = 800;

/** The fields of this page that still upload or wait: leaving the page would drop their files. */
const working = new Set();

/** The fields that run already: a field that leaves the page and comes back keeps its files and is not started twice. */
const started = new WeakSet();

let serial = 0;

function format(text, values = {}) {
    return String(text ?? '').replace(/:([a-z_]+)/g, (match, key) => (key in values ? String(values[key]) : match));
}

/** Hides an element by its attribute: an svg has no `hidden` property of its own. */
function hide(element, hidden) {
    element.toggleAttribute('hidden', hidden);
}

/** The first message of a 422. */
function refusal(data) {
    const messages = Object.values(data?.errors ?? {})[0];

    return Array.isArray(messages) && typeof messages[0] === 'string' ? messages[0] : null;
}

function start(field) {
    if (started.has(field)) {
        return;
    }

    let config;

    try {
        config = JSON.parse(field.dataset.config ?? '');
    } catch {
        return;
    }

    const texts = config.texts ?? {};
    const slot = (name, root = field) => root.querySelector('[data-slot="upload-' + name + '"]');
    const template = field.querySelector('template[data-upload-row]');
    const ui = {
        zone: slot('zone'),
        title: slot('title'),
        chooseLine: slot('choose-line'),
        choose: slot('choose'),
        rules: slot('rules'),
        dragTitle: slot('drag-title'),
        dragLine: slot('drag-line'),
        iconRest: slot('icon-rest'),
        iconDrag: slot('icon-drag'),
        input: slot('input'),
        done: slot('done'),
        doneText: slot('done-text'),
        list: slot('list'),
        summary: slot('summary'),
        errorsNote: slot('errors-note'),
        errorsOthers: slot('errors-others'),
        actions: slot('actions'),
        save: slot('save'),
        later: slot('later'),
        laterHint: slot('later-hint'),
        close: slot('close'),
        saveStatus: slot('save-status'),
        announce: slot('announce'),
    };

    if (! template || Object.values(ui).some((element) => element === null)) {
        return;
    }

    started.add(field);

    /** Every file of the field, in the order the editor gave them. */
    const rows = [];
    let active = null;
    let dragging = false;
    // The alt texts on their way to the server, so a second save waits for the first.
    let saving = null;
    let wasBusy = false;
    // The photos of a batch in the crop step, until it gives them back.
    let stepping = null;
    // The field of a new version takes one file at a time, like the field of an image or of a file field.
    const single = config.one === true || config.replace === true;
    // The file a new version goes to: {id, kind, name}, from made-cms:upload-target.
    let target = null;

    const folder = () => config.folder?.label ?? '';
    // The folder a file went into: the one of the field when the file came in.
    const folderOf = (row) => row.folder?.label ?? folder();
    const open = (row) => row.state === 'waiting' || row.state === 'uploading' || row.state === 'processing';
    // Every file asks its alt text, a document and a video too (the description of its download link, of what happens
    // in it); a photo the crop step has or had asks it there, not here.
    const describes = (row) => ! config.replace && row.state === 'done' && row.step !== 'waiting' && row.step !== 'handled';
    // The kind of a file as the browser names its type; the server reads it from the contents again.
    const kindOf = (file) => (file.type.startsWith('image/') ? 'image' : (file.type.startsWith('video/') ? 'video' : 'document'));

    function announce(text) {
        ui.announce.textContent = '';
        // A live region only speaks when its text changes after it was read.
        requestAnimationFrame(() => {
            ui.announce.textContent = text;
        });
    }

    function tell(name, detail) {
        field.dispatchEvent(new CustomEvent(name, { bubbles: true, detail }));
    }

    // Sizes read as the server writes them (MediaDetails): kB of 1000 bytes, from 1 MB on in MB with one decimal.
    const decimals = (value) => value.toFixed(1).replace('.', texts.decimal ?? '.');
    const size = (bytes) => (bytes < 1_000_000
        ? format(texts.kb, { size: Math.max(1, Math.round(bytes / 1000)) })
        : format(texts.mb, { size: decimals(bytes / 1_000_000) }));
    const sent = (bytes, total) => (total < 1_000_000 ? String(Math.round(bytes / 1000)) : decimals(bytes / 1_000_000));

    function remaining(row) {
        const elapsed = performance.now() - row.started;

        if (elapsed < ESTIMATE_AFTER || row.loaded <= 0) {
            return texts.remaining_unknown;
        }

        const seconds = (row.file.size - row.loaded) / (row.loaded / elapsed) / 1000;

        if (seconds < 20) {
            return texts.remaining_seconds;
        }

        return seconds < 90 ? texts.remaining_minute : format(texts.remaining_minutes, { count: Math.round(seconds / 60) });
    }

    function note(row) {
        const { file, result } = row;

        switch (row.state) {
            case 'waiting':
                return format(texts.waiting, { size: size(file.size) });
            case 'uploading':
                return format(texts.progress, { sent: sent(row.loaded, file.size), size: size(file.size), remaining: remaining(row) });
            case 'processing':
                // A photo is scaled down; an svg, a document and a video are stored as they came in.
                return file.type.startsWith('image/') && file.type !== 'image/svg+xml' ? texts.processing_image : texts.processing_file;
            case 'done':
                return [
                    result.meta,
                    result.kind === 'image' && result.size < file.size ? format(texts.was, { size: size(file.size) }) : null,
                ].filter(Boolean).join(' · ');
            default:
                return row.error;
        }
    }

    function paint(row) {
        const { element, file, result } = row;
        const part = (name) => slot('row-' + name, element);
        const done = row.state === 'done';
        const failed = row.state === 'error';
        const moving = row.state === 'uploading' || row.state === 'processing';
        const image = done ? result.kind === 'image' : file.type.startsWith('image/');
        const thumb = part('thumb');
        const bar = part('bar');

        element.dataset.state = row.state;

        part('mini').hidden = failed;
        part('mini').dataset.kind = image ? 'image' : 'file';
        part('cross').hidden = ! failed;
        thumb.hidden = ! (done && result.image && ! row.broken);

        const small = done ? (result.thumb ?? result.url) : null;

        if (! thumb.hidden && thumb.getAttribute('src') !== small) {
            thumb.src = small;
        }

        hide(part('icon-image'), ! image || ! thumb.hidden);
        hide(part('icon-file'), image);

        part('name').textContent = done ? result.file : (failed ? file.name + ' · ' + size(file.size) : file.name);
        part('note').textContent = note(row);

        part('percent').hidden = ! moving;
        part('percent').textContent = format(texts.percent, { percent: row.percent });
        bar.hidden = ! moving;
        bar.setAttribute('aria-valuenow', String(row.percent));
        bar.firstElementChild.style.width = row.percent + '%';

        part('cancel').hidden = row.state !== 'waiting';
        part('retry').hidden = ! (failed && row.retry);
        part('dismiss').hidden = ! failed;
        part('alt').hidden = ! describes(row);

        // What the alt text of a document or a video is; the field of one file keeps its own hint.
        if (done && ! config.one) {
            const kind = result.kind === 'document' || result.kind === 'video' ? result.kind : 'image';

            part('alt-input').placeholder = texts['alt_placeholder_' + kind] ?? part('alt-input').placeholder;
            part('alt-hint').textContent = texts['alt_hint_' + kind] ?? '';
            part('alt-hint').hidden = (texts['alt_hint_' + kind] ?? '') === '';
        }
    }

    /** The field as a whole: which moment it is in, and what a host and a screen reader hear of it. */
    function refresh() {
        const done = rows.filter((row) => row.state === 'done');
        const failed = rows.filter((row) => row.state === 'error');
        const busy = rows.some(open);
        const total = rows.length - failed.length;
        const finished = ! busy && done.length > 0;
        const describing = done.some(describes);

        field.dataset.moment = dragging ? 'drag' : (busy ? 'busy' : (done.length > 0 ? 'done' : (failed.length > 0 ? 'error' : 'rest')));

        ui.list.hidden = rows.length === 0;
        ui.summary.hidden = ! busy;
        ui.summary.textContent = busy ? format(texts.summary, { done: done.length, total }) : '';
        ui.errorsNote.hidden = failed.length === 0;
        // "The others went on" only holds when there are others.
        ui.errorsOthers.hidden = total === 0;

        ui.done.hidden = ! finished;
        ui.doneText.textContent = finished
            ? format(done.length === 1 ? texts.done_one : texts.done_many, { count: done.length, folder: folderOf(done[done.length - 1]) })
            : '';
        ui.actions.hidden = ! finished;
        ui.save.hidden = ! describing;
        ui.later.hidden = ! describing;
        ui.laterHint.hidden = ! describing;
        ui.close.hidden = describing;

        if (wasBusy && finished) {
            announce(ui.doneText.textContent);
        }

        wasBusy = busy;
        busy ? working.add(field) : working.delete(field);

        tell('made-cms:upload', {
            busy,
            done: done.length,
            total,
            failed: failed.length,
            percent: active?.percent ?? null,
            sending: active?.state === 'uploading',
        });
    }

    function fail(row, message, retry) {
        row.state = 'error';
        row.error = message ?? texts.failed;
        row.retry = retry;
        paint(row);
        announce(format(texts.refused, { name: row.file.name, error: row.error }));
    }

    function settle(row, xhr) {
        const data = xhr.response;

        if ((xhr.status === 201 || xhr.status === 200) && data?.file) {
            row.state = 'done';
            row.result = data.file;
            paint(row);
            tell('made-cms:uploaded', { file: data.file, notice: data.notice ?? null });
        } else if (xhr.status === 422) {
            fail(row, refusal(data), false);
        } else if (xhr.status === 413) {
            fail(row, texts.server_limit, false);
        } else if (xhr.status === 401 || xhr.status === 419) {
            fail(row, texts.expired, false);
        } else {
            fail(row, texts.failed, true);
        }
    }

    function upload(row) {
        const body = new FormData();
        const xhr = new XMLHttpRequest();

        body.append('file', row.file, row.file.name);

        if (! config.replace && row.folder?.id !== null && row.folder?.id !== undefined) {
            body.append('folder', String(row.folder.id));
        }

        active = row;
        row.xhr = xhr;
        row.state = 'uploading';
        row.percent = 0;
        row.loaded = 0;
        row.started = performance.now();

        xhr.open('POST', config.replace ? config.urls.upload.replace(':media', String(row.target.id)) : config.urls.upload);
        xhr.responseType = 'json';
        Object.entries({ ...HEADERS, 'X-CSRF-TOKEN': config.csrf_token ?? '' }).forEach(([name, value]) => xhr.setRequestHeader(name, value));

        xhr.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable && event.total > 0 && row.state === 'uploading') {
                // The request is a little larger than the file; the editor reads the share as bytes of the file.
                row.percent = Math.min(100, Math.floor((event.loaded / event.total) * 100));
                row.loaded = Math.round(row.file.size * (event.loaded / event.total));
                paint(row);
                refresh();
            }
        });
        xhr.upload.addEventListener('load', () => {
            row.state = 'processing';
            row.percent = 100;
            paint(row);
        });

        const end = (how) => {
            if (active !== row) {
                return;
            }

            how();
            active = null;
            pump();
        };

        xhr.addEventListener('load', () => end(() => settle(row, xhr)));
        xhr.addEventListener('error', () => end(() => fail(row, format(texts.connection, { percent: row.percent }), true)));
        xhr.addEventListener('abort', () => end(() => fail(row, format(texts.connection, { percent: row.percent }), true)));

        xhr.send(body);
        paint(row);
    }

    /** One file at a time: the next one that waits, in the order of the list. */
    function pump() {
        if (active === null) {
            const next = rows.find((row) => row.state === 'waiting');

            if (next) {
                upload(next);
            }
        }

        refresh();
        handOver();
        closeReplaced();
    }

    /**
     * The field of a new version asks nothing once its file is in: it closes, a photo once the crop step gave it back.
     * A photo the crop step never got (the field was out of sight) closes at once.
     */
    function closeReplaced() {
        if (! config.replace || rows.some(open) || stepping !== null) {
            return;
        }

        const done = rows.filter((row) => row.state === 'done');
        const waits = (row) => config.crop && row.result.croppable === true && row.step === undefined && field.checkVisibility();

        if (done.length > 0 && ! done.some(waits)) {
            close({ focus: false });
        }
    }

    /**
     * Once a batch is in, a field with config.crop hands its photos that can be cropped to the crop step on the page
     * (made-cms:crop), which asks their name and alt text too. When it gives them back the field closes, or asks the
     * alt text of the photos the editor did not get to (the step closed halfway).
     *
     * Only a field the editor still sees hands them over: a photo the server was still scaling down when the picker
     * closed never opens the step above the edit shell. And only a step that says it took them (detail.taken) holds
     * the field: without its script the field asks their alt text itself, so finishing it never waits for nothing.
     */
    function handOver() {
        const step = config.crop ? document.querySelector('[data-media-crop]') : null;
        const photos = rows.filter((row) => row.state === 'done' && row.result.croppable === true && row.step === undefined);

        if (! step || photos.length === 0 || rows.some(open) || stepping !== null || ! field.checkVisibility()) {
            return;
        }

        photos.forEach((row) => {
            row.step = 'waiting';
            paint(row);
        });
        refresh();

        let answered = null;
        const waiting = new Promise((resolve) => {
            answered = resolve;
        });
        const detail = {
            files: photos.map((row) => row.result),
            upload: true,
            replace: config.replace === true,
            taken: false,
            done: (results) => {
                if (stepping === waiting) {
                    stepping = null;
                    takeBack(photos, results);
                }

                answered();
            },
        };

        stepping = waiting;
        step.dispatchEvent(new CustomEvent('made-cms:crop', { detail }));

        // Nothing took them, or the step gave them back at once: the field asks their alt text itself.
        if (! detail.taken && stepping === waiting) {
            stepping = null;
            takeBack(photos, photos.map((row) => ({ file: row.result, handled: false })));
            answered();
        }
    }

    function takeBack(photos, results) {
        for (const row of photos) {
            const result = results.find((candidate) => candidate.file?.id === row.result.id);

            row.result = result?.file ?? row.result;
            row.step = result?.handled ? 'handled' : 'left';

            if (rows.includes(row)) {
                paint(row);
            }
        }

        const asking = rows.find(describes);

        if (asking) {
            refresh();
            slot('row-alt-input', asking.element).focus();
        } else if (rows.some((row) => row.state === 'done')) {
            // The host of a new version puts the focus where its panel was.
            close({ focus: ! config.replace });
        }
    }

    function remove(row) {
        const held = row.element.contains(document.activeElement);

        rows.splice(rows.indexOf(row), 1);
        row.element.remove();

        if (held) {
            ui.choose.focus();
        }
    }

    function add(file) {
        const element = template.content.firstElementChild.cloneNode(true);
        const part = (name) => slot('row-' + name, element);
        const row = { file, element, folder: config.folder ?? null, target, state: 'waiting', percent: 0, loaded: 0, started: 0, result: null, error: '', retry: false, broken: false, xhr: null };
        const id = 'made-cms-upload-' + (++serial);
        const name = part('name');
        const input = part('alt-input');

        // Every button of a row is named by its file, and the alt text field has a label of its own.
        name.id = id + '-name';
        ['cancel', 'retry', 'dismiss'].forEach((button) => part(button).setAttribute('aria-describedby', name.id));
        input.id = id + '-alt';
        part('alt-label').setAttribute('for', input.id);
        part('alt-error').id = id + '-alt-error';

        // The field of an image says what an alt text is for, below the input.
        const hint = slot('row-alt-hint', element);

        if (hint) {
            hint.id = id + '-alt-hint';
            input.setAttribute('aria-describedby', hint.id);
        }
        part('bar').setAttribute('aria-label', format(texts.uploading, { name: file.name }));

        part('cancel').addEventListener('click', () => {
            remove(row);
            refresh();
        });
        part('dismiss').addEventListener('click', () => {
            remove(row);
            refresh();

            // "Ander bestand": the field of a new version opens the file chooser again, in the click of the editor.
            if (config.replace) {
                ui.input.click();
            }
        });
        part('retry').addEventListener('click', () => {
            row.state = 'waiting';
            row.error = '';
            paint(row);
            ui.choose.focus();
            pump();
        });
        // A thumbnail that does not load makes way for the icon again.
        part('thumb').addEventListener('error', () => {
            row.broken = true;
            paint(row);
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                save();
            }
        });

        rows.push(row);
        ui.list.append(element);

        // A file the server refuses anyway never travels: the largest file is known here. The server checks again.
        // A field of some kinds takes no other kind, also not one that was dropped past the file chooser.
        if (Array.isArray(config.kinds) && ! config.kinds.includes(kindOf(file))) {
            fail(row, texts.wrong_kind ?? texts.not_image, false);
        } else if (config.replace && kindOf(file) !== row.target.kind) {
            fail(row, texts['kind_' + row.target.kind] ?? texts.failed, false);
        } else if (file.size > (config.limits?.max_bytes ?? Infinity)) {
            fail(row, file.type.startsWith('video/') ? texts.too_large_video : texts.too_large, false);
        } else {
            paint(row);
        }
    }

    function take(files) {
        // The field of a new version takes nothing before it knows the file it replaces.
        if (files.length === 0 || (config.replace && target === null)) {
            return;
        }

        (single ? files.slice(0, 1) : files).forEach(add);
        pump();
    }

    /**
     * Stops what is still on its way: the file whose bytes travel and the files that wait leave the field. A file the
     * server is storing already goes on. The abort of a request that never left gives no event, so the row goes here.
     */
    function stop() {
        rows.filter((row) => row.state === 'waiting').forEach(remove);

        const row = active;

        if (row?.state === 'uploading') {
            active = null;
            row.xhr.abort();
            remove(row);
        }

        pump();
    }

    /**
     * Leaves the moment "done": the files stay in the library, the field is at rest again. Errors stay. The focus goes
     * to the file chooser, unless a host finishes the field and keeps the focus where it is.
     */
    function close({ focus = true } = {}) {
        const files = rows.filter((row) => row.state === 'done').map((row) => row.result);

        rows.filter((row) => row.state === 'done').forEach(remove);
        // At rest first: the field of an image hides its zone in the moment "done", and a hidden button takes no focus.
        refresh();

        if (focus) {
            ui.choose.focus();
        }

        tell('made-cms:upload-closed', { files });
    }

    async function store(row, alt) {
        try {
            const response = await fetch(config.urls.alt.replace(':media', String(row.result.id)), {
                method: 'PUT',
                credentials: 'same-origin',
                headers: { ...HEADERS, 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf_token ?? '' },
                body: JSON.stringify({ alt }),
            });
            const data = await response.json().catch(() => null);

            if (response.ok && data?.file) {
                row.result = data.file;

                return null;
            }

            if (response.status === 422) {
                return refusal(data) ?? texts.alt_failed;
            }

            return response.status === 401 || response.status === 419 ? texts.expired : texts.alt_failed;
        } catch {
            return texts.alt_failed;
        }
    }

    function refuseAlt(row, message) {
        const part = (name) => slot('row-' + name, row.element);
        const error = part('alt-error');
        const input = part('alt-input');

        part('alt').classList.toggle('fout', message !== null);
        error.hidden = message === null;
        error.textContent = message ?? '';
        const described = [slot('row-alt-hint', row.element)?.id, message === null ? null : error.id].filter(Boolean).join(' ');

        input.toggleAttribute('aria-invalid', message !== null);
        described === '' ? input.removeAttribute('aria-describedby') : input.setAttribute('aria-describedby', described);
    }

    /** "Alt-teksten opslaan": every text the editor typed, one request per file. Without a text it is "later". */
    function save(options = {}) {
        if (rows.some(open)) {
            return Promise.resolve();
        }

        saving ??= storeAlts(options).finally(() => {
            saving = null;
        });

        return saving;
    }

    async function storeAlts({ focus = true } = {}) {
        const typed = rows.filter(describes)
            .map((row) => ({ row, alt: slot('row-alt-input', row.element).value.trim() }))
            .filter(({ row, alt }) => alt !== (row.result.alt ?? ''));
        const refused = [];

        [ui.save, ui.later].forEach((button) => button.disabled = true);
        ui.saveStatus.hidden = typed.length === 0;
        ui.saveStatus.textContent = typed.length === 0 ? '' : texts.saving;

        for (const { row, alt } of typed) {
            const message = await store(row, alt);

            refuseAlt(row, message);

            if (message !== null) {
                refused.push(row);
            }
        }

        [ui.save, ui.later].forEach((button) => button.disabled = false);
        ui.saveStatus.hidden = true;
        ui.saveStatus.textContent = '';

        if (refused.length > 0) {
            const first = refused[0];

            slot('row-alt-input', first.element).focus();
            announce(format(texts.refused, { name: first.result.file, error: slot('row-alt-error', first.element).textContent }));

            return;
        }

        if (typed.length > 0) {
            announce(texts.alts_saved);
        }

        close({ focus });
    }

    /**
     * For a host that leaves the field (made-cms:upload-finish): the moment "done" ends as with "Alt-tekst opslaan".
     * True once nothing of it is left, also while files are still on their way; false when the server refused an alt
     * text, which stays with its file and has the focus.
     */
    async function finish() {
        // The photos in the crop step come back first, with what the editor stored there.
        await stepping;

        if (rows.some(open) || ! rows.some((row) => row.state === 'done')) {
            return true;
        }

        await save({ focus: false });

        return ! rows.some((row) => row.state === 'done');
    }

    /**
     * Sends the field into another folder ("Alle bestanden" for null): the files that come in from now on go there,
     * and the line of rules says so. A file that is in the field already keeps its folder.
     */
    function follow(next) {
        const id = Number.isInteger(next?.id) ? next.id : null;

        config.folder = { id, label: id === null ? (texts.all_files ?? '') : format(texts.folder, { name: next.name ?? '' }) };

        if (texts.rules) {
            ui.rules.textContent = format(texts.rules, { folder: config.folder.label });
        }
    }

    /**
     * Points the field of a new version at the file it replaces: its words and the file chooser follow its kind. A
     * refusal belonged to the file before; a file on its way keeps its own.
     */
    function aim(media) {
        if (! config.replace || ! Number.isInteger(media?.id)) {
            return;
        }

        const kind = ['image', 'document', 'video'].includes(media.kind) ? media.kind : 'document';

        if (target?.id !== media.id) {
            rows.filter((row) => row.state === 'error').forEach(remove);
        }

        target = { id: media.id, kind, name: String(media.name ?? '') };
        ui.title.textContent = texts['title_' + kind] ?? ui.title.textContent;
        ui.choose.textContent = texts['choose_' + kind] ?? ui.choose.textContent;
        ui.rules.textContent = texts['rules_' + kind] ?? '';
        ui.input.accept = config.accepts?.[kind] ?? '';
        refresh();
    }

    /** The files of a drop; a folder is no file. */
    function dropped(transfer) {
        const items = [...(transfer.items ?? [])].filter((item) => item.kind === 'file');

        if (items.length === 0) {
            return [...(transfer.files ?? [])];
        }

        return items
            .filter((item) => ! (typeof item.webkitGetAsEntry === 'function' && item.webkitGetAsEntry()?.isDirectory))
            .map((item) => item.getAsFile())
            .filter((file) => file !== null);
    }

    const carriesFiles = (event) => [...(event.dataTransfer?.types ?? [])].includes('Files');

    function drag(over, transfer = null) {
        if (over) {
            // While dragging the browser tells how many, not which: enough for "3 bestanden".
            const count = [...(transfer?.items ?? [])].filter((item) => item.kind === 'file').length;

            ui.dragLine.textContent = config.replace
                ? format(texts['drag_' + (target?.kind ?? 'image')], { name: target?.name ?? '' })
                : (count === 0
                    ? format(texts.drag_unknown, { folder: folder() })
                    : format(count === 1 ? texts.drag_one : texts.drag_many, { count, folder: folder() }));
        }

        if (dragging === over) {
            return;
        }

        dragging = over;
        // The field keeps its height while files hang above it: a field that shrinks under the pointer would let
        // them go and take them again.
        ui.zone.style.minHeight = over ? ui.zone.offsetHeight + 'px' : '';
        field.toggleAttribute('data-drag', over);
        [ui.title, ui.chooseLine, ui.rules, ui.iconRest].forEach((element) => hide(element, over));
        [ui.dragTitle, ui.dragLine, ui.iconDrag].forEach((element) => hide(element, ! over));
        refresh();
    }

    field.addEventListener('dragenter', (event) => {
        if (carriesFiles(event)) {
            event.preventDefault();
            drag(true, event.dataTransfer);
        }
    });
    field.addEventListener('dragover', (event) => {
        if (carriesFiles(event)) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'copy';
            drag(true, event.dataTransfer);
        }
    });
    field.addEventListener('dragleave', (event) => {
        if (! field.contains(event.relatedTarget)) {
            drag(false);
        }
    });
    field.addEventListener('drop', (event) => {
        if (! carriesFiles(event)) {
            return;
        }

        event.preventDefault();
        drag(false);
        take(dropped(event.dataTransfer));
    });

    ui.choose.addEventListener('click', () => ui.input.click());
    ui.input.addEventListener('change', () => {
        take([...ui.input.files]);
        // The same file can be chosen again, after an error for instance.
        ui.input.value = '';
    });

    field.addEventListener('made-cms:upload-choose', () => ui.input.click());
    field.addEventListener('made-cms:upload-take', (event) => {
        const detail = event.detail ?? {};

        take(detail.transfer ? dropped(detail.transfer) : [...(detail.files ?? [])].filter((file) => file instanceof File));
    });
    field.addEventListener('made-cms:upload-stop', stop);
    field.addEventListener('made-cms:upload-folder', (event) => follow(event.detail?.folder ?? null));
    field.addEventListener('made-cms:upload-target', (event) => aim(event.detail?.media ?? null));
    field.addEventListener('made-cms:upload-finish', (event) => event.detail?.wait?.(finish()));
    ui.save.addEventListener('click', () => save());
    ui.later.addEventListener('click', () => close());
    ui.close.addEventListener('click', () => close());

    refresh();
}

document.querySelectorAll('[data-upload]').forEach(start);

// A host may send a field into a folder the moment it puts the field on the page, before the observer below started it:
// the field starts here first. The listener start() adds to the field still hears this event, because a node's
// listeners are read when the event reaches it.
document.addEventListener('made-cms:upload-folder', (event) => {
    if (event.target instanceof Element && event.target.matches('[data-upload]')) {
        start(event.target);
    }
}, true);

// A field a script puts on the page later starts once it is there.
new MutationObserver((records) => {
    for (const record of records) {
        for (const node of record.addedNodes) {
            if (node.nodeType === Node.ELEMENT_NODE) {
                (node.matches('[data-upload]') ? [node] : [...node.querySelectorAll('[data-upload]')]).forEach(start);
            }
        }
    }
}).observe(document.documentElement, { childList: true, subtree: true });

window.addEventListener('beforeunload', (event) => {
    if (working.size > 0) {
        event.preventDefault();
        event.returnValue = '';
    }
});
