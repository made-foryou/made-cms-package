/*
 * Made CMS: the screen of the media library (design 13.1b–z, 14.1b–z), around the list of media.js.
 *
 * - The side column ([data-media-details]) shows the file the list chose (made-cms:media-chosen): its thumbnail, what
 *   it is, its alt text with "Opslaan" (Enter saves too; choosing another file stores a typed text first and moves
 *   only once it is stored, so a refusal, a network that fails or an expired session keeps the choice where it is,
 *   with its message), the pages it is used on from GET made-cms/media/{media} with
 *   a link to their edit shell, and "Bijsnijden" for a photo, which opens the crop step (made-cms:crop). A stored alt
 *   text bubbles up as made-cms:media-changed {file}, like a crop does, so the list shows it in its tile and its numbers.
 * - "Uploaden" ([data-library-upload]) opens the file chooser of the upload field of the list, and files dragged from
 *   the computer onto the library land on a layer that hands them to the field (made-cms:upload-take). The field shows
 *   only while it has files to tell about.
 * - An address with ?file= opens with that file in the side column, also when its tile is not on the first page.
 * - The folder of the chosen file ("Map" and "Verplaatsen", design 13.1an–ar) moves it without a mouse; while the list
 *   selects (made-cms:media-selection) the column shows how many files are selected with the same folder and
 *   "Klaar". The list moves them (made-cms:media-move) and says what it did; a refusal stays below the folder. The
 *   folders come from the list (made-cms:media-folders).
 * - The actions of a file (design 13.1ba–ck): "Vervangen" opens the field of a new version (UploadField::replace(),
 *   pointed at the file with made-cms:upload-target), "Downloaden" saves it through the app, "Link kopiëren" puts its
 *   lasting link on the clipboard (or in a field to copy by hand when the browser refuses), the line of its versions
 *   comes from the server with "Terugzetten", and "Verwijderen", for the file or for the selection, asks inline and
 *   lets the list delete (made-cms:media-delete). What a new version or a version put back did is told in the message
 *   of the list (made-cms:media-notice), and the file bubbles up as made-cms:media-changed.
 *
 * Its config is JSON on the library ([data-media-library]): the addresses, the names of the kinds and its texts.
 */

const HEADERS = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

function format(text, values = {}) {
    return String(text ?? '').replace(/:([a-z_]+)/g, (match, key) => (key in values ? String(values[key]) : match));
}

function configOf(element) {
    try {
        return JSON.parse(element.dataset.config ?? '');
    } catch {
        return null;
    }
}

/** The message of a refusal keyed by a field, else null. */
async function refusal(response, field) {
    const data = await response.json().catch(() => null);
    const message = data?.errors?.[field];

    return Array.isArray(message) ? String(message[0]) : null;
}

function library(root) {
    const settings = configOf(root);
    const list = root.querySelector('[data-media]');
    const aside = root.querySelector('[data-media-details]');

    if (! settings || ! list || ! aside) {
        return;
    }

    const texts = settings.texts ?? {};
    const listSettings = configOf(list) ?? {};
    const field = list.querySelector('[data-upload]');
    const fold = list.querySelector('[data-slot="media-upload"]');
    const drop = root.querySelector('[data-slot="library-drop"]');
    const slot = (name, scope = aside) => scope.querySelector('[data-slot="details-' + name + '"]');
    const ui = {
        nothing: slot('nothing'),
        file: slot('file'),
        back: slot('back'),
        mini: slot('mini'),
        thumb: slot('thumb'),
        extension: slot('extension'),
        noAlt: slot('no-alt'),
        name: slot('name'),
        line: slot('line'),
        alt: slot('alt'),
        hint: slot('alt-hint'),
        save: slot('save'),
        changed: slot('changed'),
        status: slot('status'),
        heading: slot('usage-heading'),
        loading: slot('loading'),
        loadingText: slot('loading-text'),
        nowhere: slot('nowhere'),
        pages: slot('pages'),
        actions: slot('actions'),
        crop: slot('crop'),
        selection: slot('selection'),
        selectionCount: slot('selection-count'),
        selectionNames: slot('selection-names'),
        selectionHint: slot('selection-hint'),
        move: slot('move'),
        moveFolder: slot('move-folder'),
        moveButton: slot('move-button'),
        moveError: slot('move-error'),
        selectionDone: slot('selection-done'),
        selectionStop: slot('selection-stop'),
        replace: slot('replace'),
        download: slot('download'),
        copy: slot('copy'),
        explain: slot('explain'),
        copied: slot('copied'),
        linkField: slot('link-field'),
        link: slot('link'),
        linkClose: slot('link-close'),
        replacing: slot('replacing'),
        replaceTitle: slot('replace-title'),
        replaceWarning: slot('replace-warning'),
        replaceWarningText: slot('replace-warning-text'),
        replaceNote: slot('replace-note'),
        replaceCancel: slot('replace-cancel'),
        foot: slot('foot'),
        history: slot('history'),
        waiting: slot('waiting'),
        previous: slot('previous'),
        restore: slot('restore'),
        restoreError: slot('restore-error'),
        fileDelete: slot('delete'),
        selectionDelete: slot('selection-delete'),
    };
    const pageTemplate = aside.querySelector('template[data-details-page]');

    if (Object.values(ui).some((element) => element === null) || ! pageTemplate) {
        return;
    }

    const state = {
        // The file the side column shows, as the media endpoints give it.
        file: null,
        // The request for the places of the file.
        places: null,
        folder: listSettings.folder ?? null,
        // The folders of the library, [{id, name}], as the list loaded them last.
        folders: [],
        // The files the list selects, or null while it chooses one.
        selection: null,
        // A move on its way.
        moving: false,
        // What the server tells of the file the column shows (GET made-cms/media/{media}): its history, whether it
        // can be deleted, what replacing changes and its lasting link; null until it came.
        described: null,
        // A version on its way back.
        restoring: false,
        // The field of a new version: rest, sending (its bytes travel, it can be stopped), busy or error.
        replaceMoment: 'rest',
    };
    const replaceField = ui.replacing.querySelector('[data-upload]');
    const restoreLabel = ui.restore.textContent;
    const moveLabel = ui.moveButton.textContent;
    // The alt texts on their way to the server, by the id of their file: {alt, promise}. A file the editor comes back to
    // while its text travels shows that text, never the older one the list still holds.
    const saving = new Map();
    const saveLabel = ui.save.textContent;

    const address = (pattern, file) => pattern.replace(':media', String(file.id));
    const typed = () => ui.alt.value.trim();
    const stored = () => (state.file?.alt ?? '').trim();
    const changed = () => state.file !== null && typed() !== stored();
    // Longer than the server takes: said at once, and the choice stays until it is shorter.
    const tooLong = () => typed().length > (settings.alt_max ?? 255);

    /** The hint under the alt text: what it is for this kind, or that an image still has none; an error in its place. */
    function hint(error = null) {
        const kind = state.file?.kind ?? 'image';
        const empty = kind === 'image' && stored() === '' && typed() === '';

        ui.hint.textContent = error ?? (kind === 'image' ? (empty ? texts.alt_hint_empty : texts.alt_hint_image) : texts['alt_hint_' + kind]);
        ui.hint.classList.toggle('fout', error !== null);
        ui.hint.classList.toggle('leeg', error === null && empty);
        ui.alt.classList.toggle('fout', error !== null);
        error === null ? ui.hint.removeAttribute('role') : ui.hint.setAttribute('role', 'alert');
    }

    /** The button and the line next to it: unchanged, changed, busy, saved. */
    function moment(name) {
        ui.save.disabled = name !== 'changed' && name !== 'error';
        ui.save.textContent = name === 'busy' ? texts.saving : saveLabel;
        ui.alt.disabled = name === 'busy';
        ui.changed.hidden = name !== 'changed';
        ui.changed.textContent = texts.alt_changed ?? '';
        ui.status.textContent = name === 'saved' ? texts.saved : '';
        ui.status.classList.toggle('zichtbaar', name === 'saved');
    }

    /**
     * Shows a file. The file it shows already, as it is stored now (a crop, a move, its folder renamed), keeps a text
     * the editor typed and did not store, with its message: only what the file is follows.
     */
    function draw(file) {
        const kind = file.kind ?? 'image';
        const noAlt = kind === 'image' && (file.alt ?? '') === '';
        const thumb = file.thumb ?? null;
        const pending = saving.get(file.id) ?? null;
        const kept = pending === null && state.file?.id === file.id && changed() ? ui.alt.value : null;
        const other = state.file?.id !== file.id;

        state.file = file;

        // What the column did for the file before is not for another one.
        if (other) {
            state.described = null;
            replacing(false);
            copied('rest');
            restoreError(null);
            fileDelete.reset();
        }

        ui.nothing.hidden = true;
        ui.file.hidden = state.selection !== null;
        ui.mini.dataset.kind = kind;
        ui.thumb.hidden = thumb === null;

        if (thumb !== null && ui.thumb.getAttribute('src') !== thumb) {
            const focal = file.image?.focal ?? { x: 0.5, y: 0.5 };

            // The thumbnail, as the tiles show it: the photo itself belongs to the media element of a page.
            ui.thumb.src = thumb;
            ui.thumb.style.objectPosition = (focal.x * 100) + '% ' + (focal.y * 100) + '%';
        }

        ui.extension.textContent = file.extension ?? '';
        ui.noAlt.hidden = ! noAlt;
        ui.name.textContent = file.file ?? file.name;
        ui.line.textContent = [
            settings.kinds?.[kind],
            file.extension,
            file.meta,
            file.folder ? format(texts.detail_folder, { folder: file.folder }) : null,
        ].filter(Boolean).join(' · ');
        ui.alt.value = pending?.alt ?? kept ?? file.alt ?? '';
        ui.alt.placeholder = texts['alt_placeholder_' + kind] ?? '';
        drawActions(file);

        if (kept === null || ! changed()) {
            hint();
            moment(pending ? 'busy' : 'unchanged');
        }

        if (state.selection === null) {
            folderOf([file]);
        }
    }

    function nothing() {
        state.file = null;
        state.described = null;
        ui.nothing.hidden = state.selection !== null;
        ui.file.hidden = true;
        replacing(false);
        copied('rest');
        drawFoot();

        if (state.selection === null) {
            ui.move.hidden = true;
        }
    }

    /**
     * The actions of a file: "Bijsnijden" for a photo with an original, the address it is saved from, and what
     * downloading gives and what the link follows.
     */
    function drawActions(file) {
        const photo = file.croppable === true;

        ui.crop.hidden = ! photo;
        ui.download.href = address(settings.urls.download, file);
        ui.copy.hidden = linkOf() === null;
        ui.explain.textContent = [photo ? texts.download_photo : texts.download_file, photo ? texts.link_photo : texts.link_file].join(' ');
    }

    /** The lasting link of the file the column shows, absolute; null for a file without one. */
    function linkOf() {
        return state.described?.file?.link ?? state.file?.link ?? null;
    }

    /** "Link gekopieerd", or the link in a field to copy by hand when the browser refused; rest takes both away. */
    function copied(moment) {
        ui.copied.replaceChildren();

        if (moment !== 'rest') {
            const dot = document.createElement('span');
            const text = document.createElement('span');

            dot.className = moment === 'refused' ? 'bol let' : 'bol';
            dot.setAttribute('aria-hidden', 'true');
            text.textContent = moment === 'copied' ? texts.copied : texts.copy_refused;
            ui.copied.append(dot, text);
        }

        ui.linkField.hidden = moment !== 'refused';

        if (moment === 'refused') {
            ui.link.value = linkOf() ?? '';
            ui.link.focus();
            ui.link.select();
        }
    }

    async function copy() {
        const link = linkOf();

        if (link === null) {
            return;
        }

        try {
            await navigator.clipboard.writeText(link);
            copied('copied');
        } catch {
            // No clipboard (an address without https) or the browser refused it: the editor copies by hand.
            copied('refused');
        }
    }

    /** What the list says of the side column, in its message. */
    function tell(text) {
        if (text) {
            list.dispatchEvent(new CustomEvent('made-cms:media-notice', { detail: { text, tone: 'goed' } }));
        }
    }

    /** The file as the server answers it now, with what a page of the list said of it and the answer does not. */
    function keeping(file) {
        return { used: state.file?.used, link: state.file?.link, ...file };
    }

    /**
     * Opens or closes the field of a new version, pointed at the file the column shows: its words follow the kind of
     * the file, the warning what replacing changes on the pages it is on.
     */
    function replacing(open) {
        const file = state.file;

        ui.replacing.hidden = ! open || file === null;
        ui.replace.setAttribute('aria-expanded', String(! ui.replacing.hidden));

        if (ui.replacing.hidden) {
            return;
        }

        const kind = file.kind ?? 'image';

        ui.replaceTitle.textContent = texts['replace_title_' + kind] ?? '';
        ui.replaceNote.textContent = file.croppable === true ? texts.replace_note_photo : texts.replace_note;
        warn();
        replaceField?.dispatchEvent(new CustomEvent('made-cms:upload-target', { detail: { media: { id: file.id, kind, name: file.file ?? file.name } } }));
    }

    function warn() {
        const warning = state.described?.replace?.warning ?? null;

        ui.replaceWarning.hidden = warning === null;
        ui.replaceWarningText.textContent = warning ?? '';
    }

    /** "Annuleren": a file whose bytes travel stops; one the server is storing already lands as the new version. */
    function stopReplacing() {
        if (state.replaceMoment === 'sending') {
            replaceField?.dispatchEvent(new CustomEvent('made-cms:upload-stop'));
        }

        replacing(false);
        ui.replace.focus();
    }

    /** The line of the versions with "Terugzetten", and "Verwijderen", once the server told them. */
    function drawFoot() {
        const data = state.described;

        ui.foot.hidden = state.selection !== null || state.file === null || data === null;

        if (data !== null) {
            ui.history.textContent = data.history?.line ?? '';
            ui.waiting.hidden = data.history?.can_restore !== true;
            ui.previous.textContent = data.history?.previous ?? '';
            drawRestore();
            warn();
        }

        fileDelete.draw();
    }

    // Busy, the button keeps the focus (aria-disabled, never disabled): a second click does nothing meanwhile.
    function drawRestore() {
        ui.restore.setAttribute('aria-disabled', String(state.restoring));
        ui.restore.textContent = state.restoring ? texts.restoring : restoreLabel;

        const label = state.described?.history?.restore ?? null;

        label === null || state.restoring ? ui.restore.removeAttribute('aria-label') : ui.restore.setAttribute('aria-label', label);
    }

    function restoreError(error) {
        ui.restoreError.hidden = error === null;
        ui.restoreError.textContent = error ?? '';
        error === null ? ui.restoreError.removeAttribute('role') : ui.restoreError.setAttribute('role', 'alert');
    }

    /** "Terugzetten": the version that waits swaps places with the one the site shows. */
    async function restore() {
        const file = state.file;

        if (file === null || state.restoring) {
            return;
        }

        let response = null;
        let data = null;

        state.restoring = true;
        restoreError(null);
        drawRestore();

        try {
            response = await fetch(address(settings.urls.restore, file), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { ...HEADERS, 'X-CSRF-TOKEN': settings.csrf_token ?? '' },
            });
            data = await response.json().catch(() => null);
        } catch {
            response = null;
        }

        state.restoring = false;
        drawRestore();

        if (response?.ok && data?.file) {
            const restored = keeping(data.file);

            tell(data.notice);
            root.dispatchEvent(new CustomEvent('made-cms:media-changed', { bubbles: true, detail: { file: restored } }));

            if (state.file?.id === file.id) {
                show(restored);
            }

            return;
        }

        if (state.file?.id === file.id) {
            const message = response?.status === 422 ? data?.errors?.version?.[0] : null;

            restoreError(message ?? (response?.status === 401 || response?.status === 419 ? texts.expired : texts.restore_failed));
        }
    }

    /**
     * "Verwijderen" with its confirmation inline, for the chosen file or the selection: what describe() gives says
     * which files, whether it can, why not, and the words of the confirmation; null hides it. The list deletes
     * (made-cms:media-delete) and says what it did.
     */
    function deleter(part, describe) {
        const piece = (name) => part.querySelector('[data-slot="details-delete-' + name + '"]');
        const own = {
            button: piece('button'),
            note: piece('note'),
            kept: piece('kept'),
            ask: piece('ask'),
            text: piece('text'),
            error: piece('error'),
            yes: piece('yes'),
            no: piece('no'),
        };
        let open = false;
        let busy = false;

        function draw() {
            const what = describe();

            part.hidden = what === null;

            if (what === null) {
                open = false;

                return;
            }

            open = open && what.allowed;
            own.button.hidden = open;
            own.button.disabled = ! what.allowed;
            own.button.setAttribute('aria-expanded', String(open));
            own.note.hidden = open || ! what.note;
            own.note.textContent = what.note ?? '';
            own.ask.hidden = ! open;
            own.text.textContent = what.text;
            own.yes.textContent = busy ? texts.deleting : what.yes;
            // Busy, "Ja, verwijder" keeps the focus (aria-disabled, never disabled); confirm() ignores it meanwhile.
            own.yes.setAttribute('aria-disabled', String(busy));
            own.no.disabled = busy;
        }

        function error(message) {
            own.error.hidden = message === null;
            own.error.textContent = message ?? '';
        }

        /** Why a file stayed after all; with focus it takes the focus, as the button it came from is gone. */
        function kept(message, focus = false) {
            own.kept.hidden = message === null;
            own.kept.textContent = message ?? '';

            if (message !== null && focus) {
                own.kept.focus();
            }
        }

        function ask(on) {
            open = on;
            error(null);
            kept(null);
            draw();
            (on ? own.no : own.button).focus();
        }

        async function confirm() {
            const what = describe();

            if (what === null || busy) {
                return;
            }

            busy = true;
            error(null);
            draw();

            const result = await new Promise((resolve) => {
                const detail = { files: what.ids, selection: what.selection === true, done: resolve, taken: false };

                list.dispatchEvent(new CustomEvent('made-cms:media-delete', { detail }));

                if (! detail.taken) {
                    resolve({ ok: false, deleted: [], kept: [], error: texts.action_failed });
                }
            });

            busy = false;

            if (! result.ok) {
                draw();
                error(result.error === texts.action_failed ? what.failed : result.error);
                own.yes.focus();

                return;
            }

            open = false;
            draw();
            what.after?.(result);
        }

        own.button.addEventListener('click', () => ask(true));
        own.no.addEventListener('click', () => ask(false));
        own.yes.addEventListener('click', confirm);
        own.ask.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && ! busy) {
                // Escape closes the confirmation only, never the selection.
                event.preventDefault();
                event.stopPropagation();
                ask(false);
            }
        });

        return {
            draw,
            kept,
            reset() {
                if (! busy) {
                    open = false;
                    error(null);
                    kept(null);
                }

                draw();
            },
        };
    }

    const nameOf = (file) => file.file ?? file.name;

    /** Names as one line: "a", "a en b", "a, b en 3 andere". */
    function names(files) {
        if (files.length <= 1) {
            return files.map(nameOf).join('');
        }

        if (files.length === 2) {
            return format(texts.names_two, { first: nameOf(files[0]), second: nameOf(files[1]) });
        }

        const more = files.length - 2;

        return format(more === 1 ? texts.selected_more_one : texts.selected_more, { names: files.slice(0, 2).map(nameOf).join(', '), count: more });
    }

    const fileDelete = deleter(ui.fileDelete, () => {
        const file = state.file;
        const data = state.described;

        if (file === null || data === null) {
            return null;
        }

        const name = nameOf(file);

        return {
            ids: [file.id],
            allowed: data.removal?.allowed === true,
            note: data.removal?.note ?? null,
            text: format(data.history?.can_restore ? texts.delete_one_previous : texts.delete_one, { name }),
            yes: format(texts.delete_yes, { name }),
            failed: texts.delete_failed,
            // A page took the file in the meantime: said at the button, and the column shows where it is now.
            after(result) {
                if (result.kept.includes(file.id) && state.file?.id === file.id) {
                    fileDelete.kept(format(texts.delete_kept, { name }), true);
                    usage(state.file);
                }
            },
        };
    });

    // The selection: what can go is counted beforehand from what the list says of each file; the server checks again.
    const selectionDelete = deleter(ui.selectionDelete, () => {
        const files = state.selection;

        if (files === null || files.length === 0) {
            return null;
        }

        const free = files.filter((file) => file.used !== true);
        const used = files.filter((file) => file.used === true);
        const stay = used.length === 0 ? '' : format(used.length === 1 ? texts.kept_one : texts.kept_many, { names: names(used) });
        const all = files.length === 1
            ? format(texts.delete_one, { name: nameOf(files[0]) })
            : (files.length === 2 ? texts.delete_all_two : format(texts.delete_all_many, { count: files.length }));
        const none = files.length === 1
            ? format(texts.delete_none_one, { name: nameOf(files[0]) })
            : (files.length === 2 ? texts.delete_none_two : format(texts.delete_none_many, { count: files.length }));

        return {
            ids: files.map((file) => file.id),
            selection: true,
            allowed: free.length > 0,
            note: free.length === 0 ? none : null,
            text: used.length === 0 ? all : [format(free.length === 1 ? texts.delete_some_one : texts.delete_some, { count: free.length, total: files.length }), stay].join(' '),
            yes: free.length === 1 ? texts.delete_yes_one : format(texts.delete_yes_many, { count: free.length }),
            failed: texts.delete_failed_many,
        };
    });

    /**
     * "Map" for these files: their folder chosen when they share one ("Alle bestanden" for none), else "Kies een
     * map" first. "Verplaatsen" works once another folder is chosen.
     */
    function folderOf(files) {
        ui.move.hidden = files.length === 0;
        folderOptions(files);
        moveError(null);
    }

    /** The options of "Map" as the folders are now; with keep the folder the editor picked stays while it exists. */
    function folderOptions(files, keep = false) {
        const picked = ui.moveFolder.value;
        const shared = new Set(files.map((file) => file.folder_id ?? null));
        const current = shared.size === 1 ? String([...shared][0] ?? '') : 'none';

        ui.moveFolder.replaceChildren(
            ...(current === 'none' ? [new Option(texts.choose_folder ?? '', 'none')] : []),
            new Option(texts.all_files ?? '', ''),
            ...state.folders.map((folder) => new Option(folder.name, String(folder.id))),
        );
        ui.moveFolder.value = keep && [...ui.moveFolder.options].some((option) => option.value === picked) ? picked : current;
        ui.moveFolder.dataset.current = current;
        movable();
    }

    /** "Verplaatsen" only for another folder, and not while a move is on its way. */
    function movable() {
        const value = ui.moveFolder.value;

        ui.moveFolder.disabled = state.moving;
        ui.moveButton.disabled = state.moving || value === 'none' || value === ui.moveFolder.dataset.current;
        ui.moveButton.textContent = state.moving ? texts.saving : moveLabel;
    }

    function moveError(error) {
        ui.moveError.hidden = error === null;
        ui.moveError.textContent = error ?? '';
        ui.moveFolder.classList.toggle('fout', error !== null);
        ui.moveFolder.setAttribute('aria-invalid', String(error !== null));
        error === null ? ui.moveError.removeAttribute('role') : ui.moveError.setAttribute('role', 'alert');
    }

    /** The column while the list selects: how many, their names, and their folder; or the chosen file again. */
    function selection(files) {
        state.selection = files;
        aside.classList.toggle('selecteert', files !== null);
        ui.selection.hidden = files === null;
        ui.selectionDone.hidden = files === null;
        drawFoot();
        selectionDelete.reset();

        if (files === null) {
            ui.nothing.hidden = state.file !== null;
            ui.file.hidden = state.file === null;

            if (state.file !== null) {
                folderOf([state.file]);
            } else {
                ui.move.hidden = true;
            }

            return;
        }

        const names = files.slice(0, 3).map((file) => file.file ?? file.name).join(', ');
        const more = files.length - 3;

        ui.nothing.hidden = true;
        ui.file.hidden = true;
        ui.selectionCount.textContent = files.length === 0 ? texts.selected_none : (files.length === 1 ? texts.selected_one : format(texts.selected_many, { count: files.length }));
        ui.selectionNames.hidden = files.length === 0;
        ui.selectionNames.textContent = more <= 0 ? names : format(more === 1 ? texts.selected_more_one : texts.selected_more, { names, count: more });
        ui.selectionHint.hidden = files.length > 0;
        folderOf(files);
    }

    /** Moves the chosen file, after its typed alt text is stored, or the selection, through the list. */
    async function move() {
        const files = state.selection ?? (state.file === null ? [] : [state.file]);
        const value = ui.moveFolder.value;

        if (files.length === 0 || state.moving || value === 'none' || value === ui.moveFolder.dataset.current) {
            return;
        }

        if (state.selection === null && ! (await save())) {
            return;
        }

        const id = value === '' ? null : Number(value);
        const folder = id === null ? null : (state.folders.find((candidate) => candidate.id === id) ?? { id, name: ui.moveFolder.selectedOptions[0]?.textContent ?? '' });

        state.moving = true;
        movable();

        const result = await new Promise((resolve) => {
            const detail = { files: files.map((file) => file.id), folder, done: resolve, taken: false };

            list.dispatchEvent(new CustomEvent('made-cms:media-move', { detail }));

            if (! detail.taken) {
                resolve({ ok: false, error: texts.action_failed });
            }
        });

        state.moving = false;
        movable();

        // Without an error the list held the move for the typed alt text, whose message is at the text.
        if (! result.ok && result.error !== null) {
            moveError(result.error);
            ui.moveFolder.focus();
        }
    }

    /** The pages the file is on, from the server; a newer file drops an older answer. */
    async function usage(file) {
        state.places?.abort();

        const controller = new AbortController();

        state.places = controller;

        // The same file keeps what the server told of it until the new answer is in, so a button keeps its focus.
        if (state.described?.file?.id !== file.id) {
            state.described = null;
            drawFoot();
        }

        ui.heading.textContent = texts.usage ?? ui.heading.textContent;
        ui.loading.hidden = false;
        ui.loadingText.textContent = texts.loading;
        ui.nowhere.hidden = true;
        ui.pages.replaceChildren();

        try {
            const response = await fetch(address(settings.urls.show, file), { credentials: 'same-origin', headers: HEADERS, signal: controller.signal });
            const data = response.ok ? await response.json() : null;

            if (! data) {
                ui.loadingText.textContent = response.status === 401 || response.status === 419 ? texts.expired : texts.usage_failed;

                return;
            }

            ui.loading.hidden = true;

            if (state.file?.id === file.id) {
                state.described = data;
                ui.copy.hidden = linkOf() === null;
                drawFoot();
            }

            ui.heading.textContent = data.heading ?? '';
            ui.nowhere.hidden = (data.pages ?? []).length > 0;
            ui.pages.replaceChildren(...(data.pages ?? []).map(page));
        } catch (error) {
            if (error?.name !== 'AbortError') {
                ui.loadingText.textContent = texts.usage_failed;
            }
        } finally {
            if (state.places === controller) {
                state.places = null;
            }
        }
    }

    function page(data) {
        const element = pageTemplate.content.firstElementChild.cloneNode(true);
        const part = (name) => slot('page-' + name, element);
        const link = part('link');
        const note = part('note');

        if (data.url) {
            link.href = data.url;
            link.setAttribute('aria-label', format(texts.edit, { title: data.title }));
            part('title').textContent = data.title;
            part('name').remove();
        } else {
            link.remove();
            part('name').hidden = false;
            part('name').textContent = data.title;
        }

        note.hidden = ! data.note;
        note.textContent = data.note ?? '';

        for (const place of data.places ?? []) {
            const line = document.createElement('span');

            line.className = 'bibliotheek-plek';
            line.textContent = place.where;

            if (place.note) {
                const own = document.createElement('span');

                own.className = 'bibliotheek-noot';
                own.textContent = place.note;
                line.append(' ', own);
            }

            part('places').append(line);
        }

        return element;
    }

    function show(file) {
        draw(file);
        usage(file);
    }

    /** Stores the typed alt text of the file it shows. False when the server refused it, which then stays with its error. */
    function save() {
        const file = state.file;

        if (file === null || ! changed()) {
            return Promise.resolve(true);
        }

        if (saving.has(file.id)) {
            return saving.get(file.id).promise;
        }

        if (tooLong()) {
            moment('error');
            hint(texts.alt_long);
            ui.alt.focus();

            return Promise.resolve(false);
        }

        const entry = { alt: typed(), promise: null };

        saving.set(file.id, entry);
        moment('busy');
        entry.promise = (async () => {
            try {
                const response = await fetch(address(settings.urls.alt, file), {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: { ...HEADERS, 'Content-Type': 'application/json', 'X-CSRF-TOKEN': settings.csrf_token ?? '' },
                    body: JSON.stringify({ alt: entry.alt }),
                });

                if (! response.ok) {
                    const message = response.status === 422 ? await refusal(response, 'alt') : null;

                    if (state.file?.id === file.id) {
                        moment('error');
                        hint(message ?? (response.status === 401 || response.status === 419 ? texts.expired : texts.alt_failed));
                        ui.alt.focus();
                    }

                    return false;
                }

                const saved = (await response.json()).file;

                // The field shows the text as it is stored now, also after the column showed the file again meanwhile.
                if (state.file?.id === file.id) {
                    state.file = saved;
                    ui.alt.value = saved.alt ?? '';
                    ui.noAlt.hidden = ! (saved.kind === 'image' && (saved.alt ?? '') === '');
                    hint();
                    moment('saved');
                }

                root.dispatchEvent(new CustomEvent('made-cms:media-changed', { bubbles: true, detail: { file: saved } }));

                return true;
            } catch {
                if (state.file?.id === file.id) {
                    moment('error');
                    hint(texts.alt_failed);
                    ui.alt.focus();
                }

                return false;
            } finally {
                saving.delete(file.id);

                if (state.file?.id === file.id && ui.alt.disabled) {
                    ui.alt.disabled = false;
                }
            }
        })();

        return entry.promise;
    }

    // The side column follows the choice of the list, but a typed alt text is stored first: the choice moves once it
    // is, and stays where it is with the message when the server refuses it, the network fails or the session expired.
    // A text longer than the server takes holds it at once.
    list.addEventListener('made-cms:media-choosing', (event) => {
        if (! changed()) {
            return;
        }

        if (tooLong()) {
            event.preventDefault();
            moment('error');
            hint(texts.alt_long);
            ui.alt.focus();

            return;
        }

        event.detail?.wait?.(save());
    });
    list.addEventListener('made-cms:media-chosen', (event) => {
        const file = event.detail?.file;

        if (! file) {
            nothing();

            return;
        }

        show(file);

        // On a phone the side column is below the files: it comes into view.
        if (window.matchMedia('(max-width: 600px)').matches) {
            aside.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        }
    });

    ui.alt.addEventListener('input', () => {
        // An error stays until the text is one the server takes.
        if (ui.hint.classList.contains('fout') && changed() && tooLong()) {
            moment('error');

            return;
        }

        hint();
        moment(changed() ? 'changed' : 'unchanged');
    });
    ui.alt.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && ! event.shiftKey) {
            event.preventDefault();
            save();
        }
    });
    ui.save.addEventListener('click', () => save());

    ui.moveFolder.addEventListener('change', () => {
        moveError(null);
        movable();
    });
    ui.moveButton.addEventListener('click', move);
    ui.selectionStop.addEventListener('click', () => {
        list.dispatchEvent(new CustomEvent('made-cms:media-select', { detail: { on: false } }));
    });
    list.addEventListener('made-cms:media-selection', (event) => {
        selection(event.detail?.selecting === true ? (event.detail.files ?? []) : null);
    });
    list.addEventListener('made-cms:media-folders', (event) => {
        state.folders = event.detail?.folders ?? [];

        // The open choice keeps its folder and a refusal its message, among the folders as they are now.
        if (state.selection !== null || state.file !== null) {
            folderOptions(state.selection ?? [state.file], true);
        }
    });

    ui.replace.addEventListener('click', () => {
        if (! ui.replacing.hidden) {
            stopReplacing();

            return;
        }

        replacing(true);
        replaceField?.querySelector('[data-slot="upload-choose"]')?.focus();
    });
    ui.replaceCancel.addEventListener('click', stopReplacing);
    ui.copy.addEventListener('click', copy);
    ui.link.addEventListener('focus', () => ui.link.select());
    ui.linkClose.addEventListener('click', () => {
        copied('rest');
        ui.copy.focus();
    });
    ui.restore.addEventListener('click', restore);

    // The field of a new version: its moment decides what the panel shows around it; a version that came in is told
    // and shown, and the panel closes once the field is done (a photo after the crop step).
    replaceField?.addEventListener('made-cms:upload', (event) => {
        const detail = event.detail ?? {};

        state.replaceMoment = detail.busy ? (detail.sending ? 'sending' : 'busy') : ((detail.failed ?? 0) > 0 ? 'error' : 'rest');
        ui.replacing.dataset.moment = state.replaceMoment;
    });
    replaceField?.addEventListener('made-cms:uploaded', (event) => {
        const file = event.detail?.file;

        if (! file) {
            return;
        }

        tell(event.detail.notice);

        const replaced = state.file?.id === file.id ? keeping(file) : file;

        root.dispatchEvent(new CustomEvent('made-cms:media-changed', { bubbles: true, detail: { file: replaced } }));

        if (state.file?.id === file.id) {
            show(replaced);
        }
    });
    replaceField?.addEventListener('made-cms:upload-closed', (event) => {
        const file = (event.detail?.files ?? []).find((candidate) => candidate.id === state.file?.id);

        if (file) {
            show(keeping(file));
        }

        replacing(false);

        // The focus was in the panel or in the crop step, which are gone: it goes to "Vervangen".
        const focused = document.activeElement;

        if (state.file !== null && (focused === null || focused === document.body || ! focused.checkVisibility())) {
            ui.replace.focus();
        }
    });

    ui.crop.addEventListener('click', async () => {
        const step = document.querySelector('[data-media-crop]');

        if (! step || state.file === null || ! (await save())) {
            return;
        }

        step.dispatchEvent(new CustomEvent('made-cms:crop', { detail: { files: [state.file], opener: ui.crop } }));
    });

    // A file stored elsewhere on the page (the crop step, the upload field): the side column shows it as it is now.
    document.addEventListener('made-cms:media-changed', (event) => {
        const file = event.detail?.file;

        if (file && state.file?.id === file.id && event.target !== root) {
            show(file);
        }
    });
    field?.addEventListener('made-cms:upload-closed', (event) => {
        const file = (event.detail?.files ?? []).find((candidate) => candidate.id === state.file?.id);

        if (file) {
            show(file);
        }
    });

    // "Uploaden" opens the file chooser of the field, in the click of the editor, as the browser asks.
    document.addEventListener('click', (event) => {
        if (field && event.target instanceof Element && event.target.closest('[data-library-upload]')) {
            field.dispatchEvent(new CustomEvent('made-cms:upload-choose'));
        }
    });

    // The field shows while it has files to tell about, and folds away once it is at rest.
    field?.addEventListener('made-cms:upload', (event) => {
        const detail = event.detail ?? {};

        if (fold) {
            fold.hidden = ! (detail.busy || (detail.total ?? 0) > 0 || (detail.failed ?? 0) > 0);
        }
    });

    list.addEventListener('made-cms:media-folder', (event) => {
        state.folder = event.detail?.folder ?? null;
    });

    // Files from the computer land anywhere on the library; the layer says where they go.
    const carriesFiles = (event) => [...(event.dataTransfer?.types ?? [])].includes('Files');

    function hover(over, transfer = null) {
        if (! drop) {
            return;
        }

        if (over) {
            const count = [...(transfer?.items ?? [])].filter((item) => item.kind === 'file').length;
            const values = { count, limits: settings.limits ?? '' };

            drop.querySelector('[data-slot="library-drop-title"]').textContent = state.folder ? format(texts.drop_title_folder, { folder: state.folder.name }) : texts.drop_title;
            drop.querySelector('[data-slot="library-drop-line"]').textContent = format(count === 0 ? texts.drop_unknown : (count === 1 ? texts.drop_one : texts.drop_many), values);
        }

        drop.hidden = ! over;
        root.toggleAttribute('data-drop', over);
    }

    // Files above the open panel of a new version go there, not to the folder of the list.
    const replacingAt = (event) => ! ui.replacing.hidden && event.target instanceof Node && ui.replacing.contains(event.target);

    if (field) {
        root.addEventListener('dragenter', (event) => {
            if (carriesFiles(event)) {
                event.preventDefault();
                hover(! replacingAt(event), event.dataTransfer);
            }
        });
        root.addEventListener('dragover', (event) => {
            if (carriesFiles(event)) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'copy';
                hover(! replacingAt(event), event.dataTransfer);
            }
        });
        root.addEventListener('dragleave', (event) => {
            if (! root.contains(event.relatedTarget)) {
                hover(false);
            }
        });
        root.addEventListener('drop', (event) => {
            hover(false);

            // The upload field took a drop on itself already.
            if (! carriesFiles(event) || event.defaultPrevented) {
                return;
            }

            event.preventDefault();
            (replacingAt(event) ? replaceField : field)?.dispatchEvent(new CustomEvent('made-cms:upload-take', { detail: { transfer: event.dataTransfer } }));
        });
    }

    // Files let go beside the library (the head with "Uploaden", the bar, the menu, the crop step) would make the
    // browser leave the CMS for the file, and take a typed alt text along: the page takes them and does nothing. What
    // the library or the upload field took has defaultPrevented already.
    document.addEventListener('dragover', (event) => {
        if (carriesFiles(event) && ! event.defaultPrevented) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'none';
        }
    });
    document.addEventListener('drop', (event) => {
        if (carriesFiles(event) && ! event.defaultPrevented) {
            event.preventDefault();
        }
    });

    // An address with ?file= whose tile is not on the first page still opens that file in the side column.
    document.addEventListener('DOMContentLoaded', async () => {
        const id = listSettings.chosen;

        if (state.file !== null || id === null || id === undefined) {
            return;
        }

        try {
            const response = await fetch(address(settings.urls.show, { id }), { credentials: 'same-origin', headers: HEADERS });
            const data = response.ok ? await response.json() : null;

            if (data?.file && state.file === null) {
                draw(data.file);
                usage(data.file);
            }
        } catch {
            // The file left the library: the side column says what choosing does.
        }
    });
}

document.querySelectorAll('[data-media-library]').forEach(library);
