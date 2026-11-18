/*
 * The side panel of the edit shell: the fields of the chosen strip, saved as the draft while the editor types.
 *
 * The markup and every fixed text come from the server (the templates in cms/edit.blade.php and lang/); the
 * fields come from the definition of the strip type (GET …/strips/{id}). This file builds the controls, keeps the
 * values and sends every field of the strip to the server (PUT …/strips/{id}), which checks them against the strip
 * type. It talks JSON, so the shell and the site in its frame stay loaded. edit.js tells which strip and field are
 * chosen (made-cms:select). Every change goes to edit.js as the strip renders it (made-cms:input), so the site in the
 * frame follows while the editor types; after a save (made-cms:saved) edit.js reloads the frame only for a change it
 * could not show. The bar (bar.js) waits for the panel before it publishes (made-cms:flush) or throws the draft away
 * (made-cms:discarding), and the opbouw (structure.js) before it removes a strip (made-cms:removing). Choosing no strip
 * closes the fields; a strip that left the page (made-cms:strips) is forgotten, so putting it back loads it again.
 *
 * "Ongedaan maken" lives in the panel only: every strip keeps what the server stored before each save, for as long as
 * the shell is open, and a step back saves that again through the same endpoints.
 *
 * An image is the media element: it uploads a photo through the upload field (upload.js) and makes it the value of
 * the field. An upload runs on while the editor works elsewhere; the line at the foot of the panel tells how it goes.
 */

const SAVE_AFTER = 700;

// The words between asterisks of a field with emphasis, as Content\Strips\Emphasis finds them (and edit.js).
const EMPHASIS = /\*([^\s*](?:[^*]*[^\s*])?)\*/g;

const shell = document.querySelector('[data-edit-shell]');
const config = JSON.parse(document.querySelector('script[data-config]')?.textContent ?? '{}');
const texts = config.texts?.panel ?? {};

const slot = (name, root = shell) => root?.querySelector('[data-slot="' + name + '"]');

const ui = {
    form: slot('form'),
    stripView: slot('strip-view'),
    fields: slot('fields'),
    visible: slot('visible'),
    itemView: slot('item-view'),
    back: slot('back'),
    crumbStrip: slot('crumb-strip'),
    crumbList: slot('crumb-list'),
    crumbItem: slot('crumb-item'),
    itemPosition: slot('item-position'),
    itemError: slot('item-error'),
    itemFields: slot('item-fields'),
    done: slot('done'),
    remove: slot('remove'),
    saveRow: slot('save-row'),
    undo: slot('undo'),
    status: slot('status'),
    announce: slot('announce'),
    addedNote: slot('added-note'),
    addedText: slot('added-text'),
    removeStrip: slot('structure-remove'),
    uploadStatus: slot('upload-status'),
    uploadText: slot('upload-status-text'),
    uploadPercent: slot('upload-status-percent'),
    uploadLine: slot('upload-status-line'),
    uploadOpen: slot('upload-status-open'),
    uploadClose: slot('upload-status-close'),
    uploadUndo: slot('upload-status-undo'),
};

// Every strip the editor opened keeps its state, so going back to it loses nothing that was not saved yet.
const states = new Map();
let pages = [];
// The files of the media library the panel knows, by id: what it shows of an image and how the site renders it.
const media = new Map();
// The media elements on screen draw themselves again when a file they may show changed (the crop step).
const drawings = new Set();
let current = null;
let opening = 0;
let uid = 0;

// While the bar throws the draft away nothing is saved, so no late save brings a draft back.
let paused = false;

/* Fills :placeholders like Laravel does: the longest name first, so :id never eats into :identifier. */
function format(text, replace = {}) {
    return Object.keys(replace)
        .sort((a, b) => b.length - a.length)
        .reduce((result, key) => result.replaceAll(':' + key, String(replace[key])), text ?? '');
}

function clone(value) {
    return value === undefined ? null : structuredClone(value);
}

function template(selector) {
    return document.querySelector('template[' + selector + ']').content.firstElementChild.cloneNode(true);
}

function announce(text) {
    ui.announce.textContent = '';
    window.setTimeout(() => {
        ui.announce.textContent = text;
    }, 50);
}

/* ---------------------------------------------------------------------------------------------------------------
 * Talking to the server
 * ------------------------------------------------------------------------------------------------------------ */

function stripUrl(id, suffix = '') {
    return format(config.urls?.strip, { strip: encodeURIComponent(id) }) + suffix;
}

async function request(method, url, body) {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': config.csrf_token ?? '',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    let data = null;

    try {
        data = await response.json();
    } catch {
        data = null;
    }

    return { status: response.status, data };
}

/* ---------------------------------------------------------------------------------------------------------------
 * Opening a strip
 * ------------------------------------------------------------------------------------------------------------ */

async function open(id) {
    if (current?.id === id) {
        return;
    }

    if (current) {
        save(current);
    }

    const ticket = ++opening;
    let state = states.get(id);

    if (! state) {
        ui.form.hidden = true;
        ui.status.textContent = texts.loading ?? '';

        const { status, data } = await request('GET', stripUrl(id)).catch(() => ({ status: 0, data: null }));

        if (ticket !== opening) {
            return;
        }

        if (status !== 200 || ! data?.strip) {
            current = null;
            ui.form.hidden = false;
            ui.fields.replaceChildren();
            ui.stripView.hidden = true;
            ui.itemView.hidden = true;
            ui.status.textContent = status === 401 || status === 419 ? texts.expired : texts.load_failed;

            return;
        }

        pages = data.pages ?? pages;

        for (const file of data.strip.media ?? []) {
            media.set(file.id, file);
        }

        state = {
            id,
            label: data.strip.label,
            fields: data.strip.fields,
            values: data.strip.values,
            visible: data.strip.visible,
            // What the server holds now, and what it held before each save: the steps "Ongedaan maken" goes back.
            stored: { values: clone(data.strip.values), visible: data.strip.visible },
            history: [],
            undoing: false,
            errors: {},
            touched: new Set(),
            showAll: false,
            dirty: false,
            failed: null,
            saved: false,
            saving: null,
            added: null,
            again: false,
            timer: null,
            item: null,
            // Every change the frame is told about counts up; a save tells edit.js up to which change it stored.
            seq: 0,
            savedSeq: 0,
        };
        states.set(id, state);
    }

    current = state;
    render();
}

/* ---------------------------------------------------------------------------------------------------------------
 * Watching along: every change goes to edit.js as the strip renders it (Field::render()), so the site in the frame
 * can show it while the editor types. edit.js decides whether it can; a change it cannot show reloads the frame after
 * the next save.
 * ------------------------------------------------------------------------------------------------------------ */

/* The addresses the validation of a link accepts (Link::URL_PATTERN), so the frame never gets one the site would not render. */
const ADDRESS = /^(?:https?:\/\/\S+|mailto:\S+|tel:\S+|\/(?![/\\])\S*|#\S*)$/i;

/* A link renders as its address; a page only while it is published, as on the site. */
function linkUrl(value) {
    if (value?.page !== undefined && value?.page !== null) {
        const page = pageOf(value.page);

        return page?.status === 'published' ? page.path : null;
    }

    return typeof value?.url === 'string' && value.url.length <= 2048 && ADDRESS.test(value.url) ? value.url : null;
}

function rendered(field, value) {
    switch (field.type) {
        case 'link':
            return linkUrl(value);
        case 'button': {
            const url = linkUrl(value?.link);

            return typeof value?.text === 'string' && value.text !== '' && url !== null ? { text: value.text, url } : null;
        }
        case 'heading':
            return typeof value?.text === 'string' && value.text !== '' ? { text: value.text, tag: value.tag ?? field.tag } : null;
        case 'items':
            return null;
        case 'image':
            // The id of a file, rendered as the server described it: {url, alt, width, height, focal}.
            return media.get(value)?.image ?? null;
        case 'rich_text': {
            // edit.js puts rich text into the site as HTML, so it only ever gets the markup the panel builds itself.
            const html = typeof value === 'string' ? richHtml(new DOMParser().parseFromString(value, 'text/html').body) : '';

            return html === '' ? null : html;
        }
        default:
            return value ?? null;
    }
}

/* The text of a field with emphasis as a visitor reads it, without the asterisks around its emphasis. */
function withoutEmphasis(text) {
    return text.replace(EMPHASIS, '$1');
}

/*
 * One field of the strip changed: "kop", or "knoppen.1.knop" in an item, with the number of items so edit.js knows
 * the site shows the same list. A list itself ("knoppen") changes when an item is added, removed or moved. A text or
 * heading with emphasis says so, so edit.js shows the words between asterisks as an <em>.
 */
function liveInput(state, path, field, value) {
    const [key, , sub] = path.split('.');

    shell.dispatchEvent(new CustomEvent('made-cms:input', {
        detail: {
            id: state.id,
            path,
            type: field.type,
            value: rendered(field, value),
            count: sub === undefined ? null : (state.values[key]?.length ?? 0),
            seq: ++state.seq,
            emphasis: field.emphasis === true,
        },
    }));
}

/* The values changed at once ("Ongedaan maken"): every field that differs goes to the frame, a list as a whole. */
function followChanges(state, before, after) {
    for (const field of state.fields) {
        if (! same(before?.[field.key] ?? null, after?.[field.key] ?? null)) {
            liveInput(state, field.key, field, after?.[field.key] ?? null);
        }
    }
}

/* ---------------------------------------------------------------------------------------------------------------
 * Saving
 * ------------------------------------------------------------------------------------------------------------ */

function changed(state) {
    // Another change of the strip: "Ongedaan maken" on the line would no longer take back what the picker added.
    if (picked?.state === state) {
        picked = null;
        showUploads();
    }

    state.dirty = true;
    state.failed = null;
    window.clearTimeout(state.timer);
    state.timer = window.setTimeout(() => save(state), SAVE_AFTER);
    showStatus(state);
}

/* Leaving a field saves it right away, so the frame shows it without waiting for the pause after typing. */
function leave(state, path) {
    state.touched.add(path);

    if (state.dirty) {
        save(state);
    } else {
        showErrors(state);
    }
}

/* Every list in the values, with the item objects as they are now: new items get their id from the answer. */
function listsOf(state) {
    return state.fields
        .filter((field) => field.type === 'items')
        .map((field) => ({ key: field.key, items: [...(state.values[field.key] ?? [])] }));
}

async function save(state) {
    window.clearTimeout(state.timer);

    if (paused) {
        return null;
    }

    if (state.saving) {
        state.again = state.again || state.dirty;

        return state.saving;
    }

    if (! state.dirty) {
        showErrors(state);

        return null;
    }

    state.dirty = false;
    const undoing = state.undoing;
    const lists = listsOf(state);
    const values = clone(state.values);
    const upTo = state.seq;

    state.undoing = false;

    state.saving = request('PUT', stripUrl(state.id), { values })
        .catch(() => ({ status: 0, data: null }))
        .then(({ status, data }) => {
            // An answer that is no strip (a proxy page, an empty body) did not store anything the panel can trust.
            if (status === 200 && data?.strip) {
                // A new item gets its id here, and so does an item that came back with "Ongedaan maken".
                for (const { key, items } of lists) {
                    (data.strip.values[key] ?? []).forEach((stored, index) => {
                        if (items[index] && items[index].id !== stored.id) {
                            items[index].id = stored.id;
                        }
                    });
                }

                remember(state, { values: clone(data.strip.values), visible: state.stored.visible }, undoing);
                state.errors = {};
                state.failed = null;
                state.saved = true;
                state.savedSeq = Math.max(state.savedSeq, upTo);
                shell.dispatchEvent(new CustomEvent('made-cms:saved', { detail: { id: state.id, seq: upTo } }));
            } else if (status === 422) {
                state.errors = data?.errors ?? {};
                state.failed = 'invalid';
            } else {
                state.failed = status === 401 || status === 419 ? 'expired' : 'failed';
            }
        })
        .finally(() => {
            state.saving = null;

            if (state === current) {
                showErrors(state);
                refreshRows(state);
            }

            showStatus(state);

            if (state.again) {
                state.again = false;
                state.dirty = true;
                save(state);
            }
        });

    showStatus(state);

    return state.saving;
}

function showStatus(state) {
    if (state !== current) {
        return;
    }

    const count = Object.keys(state.errors).length;
    let text = state.saved ? texts.saved : '';

    if (state.saving) {
        text = texts.saving;
    } else if (state.dirty) {
        text = texts.unsaved;
    } else if (state.failed === 'invalid') {
        text = count === 1 ? texts.invalid_one : format(texts.invalid_many, { count });
    } else if (state.failed) {
        text = texts[state.failed];
    }

    ui.status.textContent = text ?? '';
    ui.status.dataset.state = state.saving ? 'saving' : (state.dirty ? 'unsaved' : (state.failed ? 'error' : (state.saved ? 'saved' : '')));
    ui.undo.disabled = ! undoable(state);
}

function unsaved() {
    return [...states.values()].some((state) => state.dirty || state.saving || state.failed);
}

/* ---------------------------------------------------------------------------------------------------------------
 * Errors: the server keys them by the path of a field ("kop", "knoppen.0.knop.text"). A message shows once the
 * editor left that field, finished the item or pressed "Opslaan als concept", never while a new item is still empty.
 * ------------------------------------------------------------------------------------------------------------ */

function errorShown(state, path) {
    if (state.showAll || state.touched.has(path)) {
        return true;
    }

    return [...state.touched].some((touched) => touched.startsWith('item:') && path.startsWith(touched.slice(5) + '.'));
}

function showErrors(state) {
    if (state !== current) {
        return;
    }

    for (const element of ui.form.querySelectorAll('[data-path]')) {
        const path = element.dataset.path;
        const message = state.errors[path]?.[0];
        const shown = message !== undefined && errorShown(state, path);
        const error = element.querySelector(':scope > [data-slot="error"]');
        const input = element.input;

        element.classList.toggle('fout', shown);
        error.hidden = ! shown;
        error.textContent = shown ? message : '';
        input?.setAttribute('aria-invalid', String(shown));
    }

    if (state.item) {
        const path = state.item.key + '.' + state.item.index;
        const message = state.errors[path]?.[0];

        ui.itemError.hidden = message === undefined;
        ui.itemError.textContent = message ?? '';
    }

    showStatus(state);
}

/* Errors of a list keep pointing at the old places once its items move; the next save tells where they are now. */
function forgetErrorsOf(state, key) {
    for (const path of Object.keys(state.errors)) {
        if (path.startsWith(key + '.')) {
            delete state.errors[path];
        }
    }
}

/* ---------------------------------------------------------------------------------------------------------------
 * Rendering
 * ------------------------------------------------------------------------------------------------------------ */

function render() {
    const state = current;

    drawings.clear();

    ui.form.hidden = false;
    ui.visible.checked = state.visible;

    if (state.item) {
        renderItem(state);
    } else {
        ui.itemView.hidden = true;
        ui.stripView.hidden = false;
        ui.saveRow.hidden = false;
        ui.removeStrip.hidden = false;
        ui.fields.replaceChildren(...state.fields.map((field) => control(state, field, state.values, field.key)));
    }

    showAdded(state);

    showErrors(state);
}

function describedBy(element, input) {
    const hint = element.querySelector(':scope > [data-slot="hint"]');
    const emphasis = element.querySelector(':scope > [data-slot="emphasis-hint"]:not([hidden])');
    const error = element.querySelector(':scope > [data-slot="error"]');
    const ids = [];

    for (const part of [hint, emphasis, error]) {
        if (part) {
            part.id ||= 'paneel-' + (++uid);
            ids.push(part.id);
        }
    }

    input?.setAttribute('aria-describedby', ids.join(' '));
}

/*
 * One control for one field. The value lives in owner[key]: the values of the strip or an item of a list, so the
 * same controls work at the top of the strip and inside an item. A change goes to the frame as this field, unless
 * the control is part of a field (a part of a button), which then tells the frame itself.
 */
function control(state, field, owner, path, follow = null) {
    const element = template('data-field="' + field.type + '"');
    const label = slot('label', element);
    const hint = slot('hint', element);
    const input = slot('input', element);
    const key = path.split('.').pop();

    element.dataset.path = path;
    element.input = input;
    label.textContent = field.label;

    if (hint) {
        hint.textContent = field.hint ?? hint.dataset.default ?? '';
        hint.hidden = hint.textContent === '';
    }

    // How to give a word emphasis, below the hint of the developer. A heading has it below its text, not in it.
    const emphasis = slot('emphasis-hint', element);

    if (emphasis) {
        emphasis.hidden = field.emphasis !== true;
    }

    if (input) {
        input.id = 'paneel-' + (++uid);
        input.setAttribute('aria-required', String(field.required));

        if (label.tagName === 'LABEL') {
            label.htmlFor = input.id;
        } else {
            label.id = 'paneel-' + (++uid);
            input.setAttribute('aria-labelledby', label.id);
        }
    }

    const set = (value) => {
        owner[key] = value;
        (follow ?? (() => liveInput(state, path, field, value)))();
        changed(state);
    };

    switch (field.type) {
        case 'text':
        case 'long_text':
            input.value = owner[key] ?? '';
            input.maxLength = field.max_length;
            input.addEventListener('input', () => set(input.value === '' ? null : input.value));
            break;
        case 'rich_text':
            richText(element, input, owner[key], set);
            break;
        case 'link':
            linkControl(element, input, owner[key], set);
            break;
        case 'choice':
            choice(input, field, owner[key], set);
            break;
        case 'button':
            button(state, element, field, owner, path);
            break;
        case 'heading':
            heading(state, element, field, owner, path);
            break;
        case 'items':
            items(state, element, field, owner, path);
            break;
        case 'image':
            image(state, element, field, owner, path, set);
            break;
    }

    describedBy(element, input);
    element.addEventListener('focusout', (event) => {
        // The picker of an image is part of the field while it is open: its choice saves the field.
        if (event.relatedTarget?.closest?.('[data-media-picker]')) {
            return;
        }

        if (! element.contains(event.relatedTarget) && field.type !== 'items' && field.type !== 'button' && field.type !== 'heading') {
            leave(state, path);
        }
    });

    return element;
}

function choice(select, field, value, set) {
    if (! field.required) {
        select.append(template('data-part="no-choice"'));
    }

    for (const option of field.options) {
        select.append(new Option(option.label, option.value));
    }

    select.value = value ?? '';
    select.addEventListener('change', () => set(select.value === '' ? null : select.value));
}

/* ---------------------------------------------------------------------------------------------------------------
 * Images: the media element (design 13.2, 13.3). The field holds the id of a file of the media library; the panel
 * shows the photo with its name, size, folder and alt text, that the field holds none, or that its file left Media.
 * "Kiezen uit media" opens the picker (media.js, the dialog [data-media-picker] in the shell) and the photo chosen
 * there is the value of the field. "Uploaden" opens the upload field below it, and the photo it takes is the value of
 * the field at once; its alt text follows once the editor saved it. Cropping (#54) follows.
 *
 * A photo goes into the folder the editor opened last in the picker (config.folder, then made-cms:media-folder), also
 * the photo of an upload field that is open already.
 *
 * An upload runs on while the editor works elsewhere. The panel keeps the field of every upload and hangs it in again
 * when it draws the field anew, so coming back shows how far it is. A photo that comes in goes to its field where it
 * is now; a strip that left the page, or an item "Ongedaan maken" took away, no longer gets it and the photo stays in
 * Media only.
 * ------------------------------------------------------------------------------------------------------------ */

// Every upload field the panel opened, until the editor closes it.
const uploads = new Set();

// The place of the photo that came in out of sight, for the line at the foot of the panel until the editor chooses a
// strip or closes the line.
let arrived = null;

// The items the picker added to a list, said on the line at the foot of the panel with "Ongedaan maken" until the
// editor chooses a strip, changes that strip again or closes the line: {state, text}.
let picked = null;

// The items the picker added, marked "Nieuw" in their list until the editor chooses a strip.
let fresh = new WeakSet();

// The upload whose failure the line shows, for "Open dat veld" and its cross.
let shown = null;

// The folder an upload of the panel goes into: {id, name}, or null for "Alle bestanden".
let folder = config.folder ?? null;

/* Sends an upload field of the panel into the folder of the moment. */
function follow(upload) {
    upload.element.querySelector('[data-upload]')?.dispatchEvent(new CustomEvent('made-cms:upload-folder', { detail: { folder } }));
}

/* The strip or item a field is in, as the panel names it: "Hero", "Kaarten › Kaart 2". */
function stripPlace(state, path) {
    const [list, index] = path.split('.');
    const parts = [state.label];

    if (path.includes('.')) {
        const items = state.fields.find((candidate) => candidate.key === list);

        parts.push(format(texts.item, { item: items?.item_label ?? '', n: Number(index) + 1 }));
    }

    return parts.join(' › ');
}

/* Where an image is, as the line at the foot of the panel names it: "Hero › Foto", "Kaarten › Kaart 2 › Icoon". */
function placeOf(state, field, path) {
    return stripPlace(state, path) + ' › ' + field.label;
}

/*
 * The values that hold the field of an upload now, with its path: the values of the strip, or its item wherever it
 * went in the list (by its id once "Ongedaan maken" put back a copy). Null once the strip or the item is gone.
 */
function where(upload) {
    const { state, list, key } = upload;

    if (states.get(state.id) !== state) {
        return null;
    }

    if (list === null) {
        return { owner: state.values, path: key };
    }

    const items = Array.isArray(state.values[list]) ? state.values[list] : [];
    let index = items.indexOf(upload.item);

    if (index < 0 && upload.item.id !== null && upload.item.id !== undefined) {
        index = items.findIndex((candidate) => candidate.id === upload.item.id);
    }

    if (index < 0) {
        return null;
    }

    upload.item = items[index];

    return { owner: items[index], path: list + '.' + index + '.' + key };
}

function uploadOf(state, owner, key) {
    return [...uploads].find((upload) => upload.state === state && upload.key === key && where(upload)?.owner === owner) ?? null;
}

/*
 * The line at the foot of the panel: the uploads on their way, with how far they are as a line along its top; else an
 * upload that failed out of sight, with a way to its field; else the photo that came in out of sight. The editor
 * closes a result with its cross; a photo still on its way stays.
 */
function showUploads() {
    const busy = [...uploads].filter((upload) => upload.busy);
    const failed = [...uploads].find((upload) => upload.failed && ! upload.seen) ?? null;
    let text = '';
    let state = '';
    let percent = null;

    if (busy.length > 0) {
        percent = Math.round(busy.reduce((sum, upload) => sum + (upload.percent ?? 0), 0) / busy.length);
        text = busy.length === 1 ? format(texts.upload_busy, { place: busy[0].place }) : format(texts.upload_busy_many, { count: busy.length });
        state = 'busy';
    } else if (failed) {
        text = format(texts.upload_failed, { place: failed.place });
        state = 'failed';
    } else if (arrived) {
        text = format(texts.upload_done, { place: arrived });
        state = 'done';
    } else if (picked) {
        text = picked.text;
        state = 'done';
    }

    // The line speaks only when its text changes, not with every percent.
    if (ui.uploadText.textContent !== text) {
        ui.uploadText.textContent = text;
    }

    ui.uploadPercent.textContent = percent === null ? '' : format(texts.upload_percent, { percent });
    ui.uploadLine.style.width = (percent ?? 0) + '%';
    ui.uploadOpen.hidden = state !== 'failed' || where(failed) === null;
    ui.uploadUndo.hidden = busy.length > 0 || failed !== null || arrived !== null || picked === null;
    ui.uploadClose.hidden = state !== 'failed' && state !== 'done';
    ui.uploadStatus.dataset.state = state;
    shown = failed;
}

/* Closes the upload field of an image once no photo is on its way; the files it took stay in Media. */
function closeUpload(upload) {
    if (upload.busy) {
        return;
    }

    const focused = upload.element.contains(document.activeElement);

    uploads.delete(upload);
    upload.element.remove();
    upload.draw?.();

    if (focused) {
        upload.button?.focus();
    }

    showUploads();
}

/* Opens the upload field of an image below it. upload.js starts the field once it is on the page. */
function openUpload(state, field, owner, path) {
    const list = path.includes('.') ? path.split('.')[0] : null;
    const element = template('data-part="upload"');
    const cancel = slot('image-upload-cancel', element);
    const upload = {
        state,
        field,
        list,
        item: list === null ? null : owner,
        key: path.split('.').pop(),
        element,
        busy: false,
        failed: false,
        seen: false,
        // A photo the editor chose in the picker while this one travelled: this one lands in Media only.
        superseded: false,
        percent: null,
        place: placeOf(state, field, path),
        waiting: [],
        draw: null,
        button: null,
    };
    const input = element.querySelector('[data-upload]');
    const busyHint = slot('image-upload-busy', element);

    // "Annuleren" stops a photo whose bytes still travel; one the server is storing lands in Media and stays open.
    cancel.addEventListener('click', () => {
        if (upload.busy) {
            input.dispatchEvent(new CustomEvent('made-cms:upload-stop'));
        }

        closeUpload(upload);
    });

    input.addEventListener('made-cms:upload', (event) => {
        const { busy, done, failed, percent, sending } = event.detail;

        if (busy && ! upload.busy) {
            const spot = where(upload);

            upload.place = spot ? placeOf(upload.state, upload.field, spot.path) : upload.place;
            upload.seen = false;
            upload.superseded = false;
        }

        upload.busy = busy;
        upload.percent = percent;
        upload.failed = ! busy && failed > 0;
        // An error the editor sees in the field needs no line at the foot of the panel.
        upload.seen ||= upload.failed && element.isConnected;
        cancel.hidden = busy ? ! sending : done > 0;
        busyHint.hidden = ! busy;

        if (! busy) {
            upload.waiting.splice(0).forEach((resolve) => resolve());
        }

        showUploads();
    });

    input.addEventListener('made-cms:uploaded', (event) => {
        const file = event.detail.file;
        const spot = where(upload);

        media.set(file.id, file);

        if (spot && ! upload.superseded) {
            spot.owner[upload.key] = file.id;
            liveInput(upload.state, spot.path, upload.field, file.id);
            changed(upload.state);
            leave(upload.state, spot.path);
            // Next to the field the photo shows itself; the line tells only an editor who is elsewhere.
            arrived = element.isConnected ? null : placeOf(upload.state, upload.field, spot.path);
        }

        upload.draw?.();
        showUploads();
    });

    // The editor left the moment "done": the alt text as it is stored goes to the panel and the frame.
    input.addEventListener('made-cms:upload-closed', (event) => {
        const spot = where(upload);

        for (const file of event.detail.files ?? []) {
            media.set(file.id, file);

            if (spot && spot.owner[upload.key] === file.id) {
                liveInput(upload.state, spot.path, upload.field, file.id);
            }
        }

        closeUpload(upload);
    });

    uploads.add(upload);

    return upload;
}

/* Waits for every photo on its way, so publishing takes the photo the editor is uploading. */
async function landed() {
    for (let busy = [...uploads].filter((upload) => upload.busy); busy.length > 0; busy = [...uploads].filter((upload) => upload.busy)) {
        await Promise.all(busy.map((upload) => new Promise((resolve) => upload.waiting.push(resolve))));
    }
}

function image(state, element, field, owner, path, set) {
    const key = path.split('.').pop();
    const label = slot('label', element);
    const hint = slot('hint', element);
    const card = slot('image-card', element);
    const thumb = slot('image-thumb', element);
    const focal = slot('image-focal', element);
    const alt = slot('image-alt', element);
    const button = slot('image-upload', element);
    const pick = slot('image-pick', element);
    const crop = slot('image-crop', element);
    const remove = slot('image-remove', element);
    const area = slot('image-upload-area', element);

    label.id ||= 'paneel-' + (++uid);
    area.id = 'paneel-' + (++uid);
    element.setAttribute('aria-labelledby', label.id);
    button.setAttribute('aria-controls', area.id);
    remove.setAttribute('aria-label', format(texts.image_remove, { field: field.label }));

    if (! hint.hidden) {
        hint.id ||= 'paneel-' + (++uid);
        element.setAttribute('aria-describedby', hint.id);
    }

    // Clicking the image on the site puts the focus on "Uploaden".
    element.focusTarget = () => button;

    // A thumbnail that does not load makes way for the icon.
    thumb.addEventListener('error', () => {
        thumb.hidden = true;
    });

    const draw = () => {
        const value = owner[key] ?? null;
        const file = value === null ? null : (media.get(value) ?? null);
        const shown = file?.image ? file : null;
        const gone = value !== null && shown === null;
        const text = shown?.alt ?? '';

        card.dataset.state = shown ? 'filled' : (gone ? 'gone' : 'empty');
        slot('image-preview', element).hidden = shown === null;
        slot('image-file', element).hidden = shown === null;
        slot('image-none', element).hidden = value !== null;
        slot('image-empty', element).hidden = value !== null;
        slot('image-empty-text', element).hidden = value !== null;
        slot('image-gone', element).hidden = ! gone;
        slot('image-gone-icon', element).hidden = ! gone;
        remove.hidden = value === null;
        // Only a photo with an original to cut from can be cropped: not an svg, not an animated gif.
        crop.hidden = shown?.croppable !== true;
        button.setAttribute('aria-expanded', String(uploadOf(state, owner, key) !== null));

        if (shown === null) {
            return;
        }

        const position = (shown.image.focal.x * 100) + '% ' + (shown.image.focal.y * 100) + '%';

        if (thumb.getAttribute('src') !== shown.url) {
            thumb.hidden = false;
            thumb.src = shown.url;
        }

        thumb.style.objectPosition = position;
        focal.style.left = (shown.image.focal.x * 100) + '%';
        focal.style.top = (shown.image.focal.y * 100) + '%';
        slot('image-extension', element).textContent = shown.extension ?? '';
        slot('image-name', element).textContent = shown.name;
        slot('image-meta', element).textContent = [shown.meta, shown.folder ? format(texts.image_folder, { folder: shown.folder }) : null]
            .filter(Boolean)
            .join(' · ');
        slot('image-no-alt', element).hidden = text !== '';
        alt.textContent = text === '' ? texts.image_no_alt : format(texts.image_alt, { alt: text });
        alt.dataset.alt = text === '' ? 'missing' : 'set';
    };

    // An upload of this field that is still open comes back below it; an error in it is in sight now.
    const open = uploadOf(state, owner, key);

    if (open) {
        open.draw = draw;
        open.button = button;
        open.seen ||= open.failed;
        area.append(open.element);
        showUploads();
    }

    button.addEventListener('click', () => {
        let upload = uploadOf(state, owner, key);

        // A field without a photo on its way closes again; one that is busy or done keeps its files.
        if (upload && ! slot('image-upload-cancel', upload.element).hidden) {
            closeUpload(upload);

            return;
        }

        if (! upload) {
            upload = openUpload(state, field, owner, path);
            area.append(upload.element);
            follow(upload);
        }

        upload.draw = draw;
        upload.button = button;
        draw();
        slot('upload-choose', upload.element)?.focus();
    });

    // The picker opens with the photo of the field chosen; "Deze gebruiken" makes its choice the value of the field,
    // wherever the field is now, and a photo of this field that still travels no longer overwrites it. Opened for an
    // item of a list, it chooses several photos: the first is the value of this field, every next one a new item
    // right after it, as long as the list takes them.
    pick.addEventListener('click', () => {
        const value = owner[key] ?? null;
        const file = value === null ? null : (media.get(value) ?? null);
        const list = path.includes('.') ? path.split('.')[0] : null;
        const spot = { state, list, item: list === null ? null : owner, key };

        document.querySelector('[data-media-picker]')?.dispatchEvent(new CustomEvent('made-cms:pick', {
            detail: {
                field: field.label,
                place: stripPlace(state, path),
                hint: field.hint ?? null,
                file: file?.image ? file : null,
                many: manyFor(state, list, owner),
                opener: pick,
                choose: (files) => {
                    const now = where(spot);

                    files.forEach((chosen) => media.set(chosen.id, chosen));

                    if (! now || files.length === 0) {
                        return;
                    }

                    const travelling = uploadOf(state, now.owner, key);

                    if (travelling?.busy) {
                        travelling.superseded = true;
                    }

                    now.owner[key] = files[0].id;
                    liveInput(state, now.path, field, files[0].id);

                    const added = list === null ? [] : addItems(state, list, now.owner, key, files.slice(1));

                    changed(state);
                    leave(state, now.path);
                    draw();

                    if (added.length > 0) {
                        itemPlace(state);
                        tellPicked(state, list, now.owner, added);
                    }
                },
            },
        }));
    });

    // The crop step changes the file itself, on every page: the value of the field stays its id.
    crop.addEventListener('click', () => {
        const value = owner[key] ?? null;
        const file = value === null ? null : (media.get(value) ?? null);

        if (file?.croppable) {
            document.querySelector('[data-media-crop]')?.dispatchEvent(new CustomEvent('made-cms:crop', {
                detail: { files: [file], opener: crop },
            }));
        }
    });

    remove.addEventListener('click', () => {
        set(null);
        leave(state, path);
        draw();
        button.focus();
    });

    drawings.add(draw);
    draw();
}

/*
 * A file changed in the crop step (made-cms:media-changed): its new address, size, focal point, name or alt text. The
 * media elements show it at once. The frame reloads to show it (edit.js); a field of a strip that still holds a change
 * of its own goes to the frame again, so the reloaded page does not get the former address of the file patched in.
 */
function mediaChanged(file) {
    media.set(file.id, file);
    drawings.forEach((draw) => draw());

    for (const state of states.values()) {
        for (const field of state.fields) {
            if (field.type === 'image' && state.values[field.key] === file.id) {
                liveInput(state, field.key, field, file.id);
            }

            if (field.type === 'items' && Array.isArray(state.values[field.key])) {
                state.values[field.key].forEach((item, index) => {
                    for (const sub of field.fields ?? []) {
                        if (sub.type === 'image' && item?.[sub.key] === file.id) {
                            liveInput(state, field.key + '.' + index + '.' + sub.key, sub, file.id);
                        }
                    }
                });
            }
        }
    }
}

/* A button is a text and a link: two parts of one value, {text, link}, or nothing when both are empty. */
function button(state, element, field, owner, path) {
    const parts = slot('parts', element);
    const key = path.split('.').pop();
    const write = (part, value) => {
        const next = { text: owner[key]?.text ?? null, link: owner[key]?.link ?? null, [part]: value };

        owner[key] = next.text === null && next.link === null ? null : next;
    };

    // The parts read and write the button through these, so each part works like a field of its own.
    const holder = {
        get text() { return owner[key]?.text ?? null; },
        set text(value) { write('text', value); },
        get link() { return owner[key]?.link ?? null; },
        set link(value) { write('link', value); },
    };

    // The frame shows a button as a whole: its text and the address it goes to.
    const follow = () => liveInput(state, path, field, owner[key]);

    parts.append(
        control(state, { key: 'text', type: 'text', label: field.parts.text, hint: null, required: field.required, max_length: 255 }, holder, path + '.text', follow),
        control(state, { key: 'link', type: 'link', label: field.parts.link, hint: null, required: field.required }, holder, path + '.link', follow),
    );
}

/*
 * A heading is a text with its level, {text, tag}. The panel edits its text; the heading keeps its level, or gets the
 * default level of the field when it had none. Whoever opens Meta-overzicht (config.heading_levels) chooses the level
 * next to the name of the field; anyone else sees it as a label, and the server keeps it for them.
 */
function heading(state, element, field, owner, path) {
    const parts = slot('parts', element);
    const key = path.split('.').pop();

    // The text reads and writes the heading through this, so it works like a field of its own.
    const holder = {
        get text() { return owner[key]?.text ?? null; },
        set text(value) { owner[key] = { text: value, tag: owner[key]?.tag ?? field.tag }; },
    };

    // The frame shows a heading as a whole: its text in the element of its level.
    const follow = () => liveInput(state, path, field, owner[key]);

    // The name of the field stands above the text with the level next to it, so the text needs no visible label.
    const text = control(state, { key: 'text', type: 'text', label: field.label, hint: null, required: field.required, max_length: field.max_length }, holder, path + '.text', follow);

    slot('label', text).classList.add('sr-only');
    parts.append(text);
    level(state, element, field, owner, path, follow);

    // The hints below the heading describe its text too.
    const input = slot('input', text);
    const hints = [...element.querySelectorAll(':scope > [data-slot="hint"], :scope > [data-slot="emphasis-hint"], :scope > [data-slot="level-hint"]')].filter((hint) => ! hint.hidden);

    input.setAttribute('aria-describedby', [input.getAttribute('aria-describedby'), ...hints.map((hint) => {
        hint.id ||= 'paneel-' + (++uid);

        return hint.id;
    })].filter(Boolean).join(' '));
}

/*
 * The level of a heading: a button with a list of the levels the field offers, each with what it means (design
 * 11.18–11.19), or a label for an editor who does not choose levels (11.20). Choosing saves at once; the frame reloads
 * after the save when the site printed another element. In an item the level belongs to that item (11.20b).
 */
function level(state, element, field, owner, path, follow) {
    const key = path.split('.').pop();
    const choice = slot('choice', element);
    const label = slot('label', element);
    const button = slot('level', element);
    const fixed = slot('level-fixed', element);
    const list = slot('levels', element);
    const hint = slot('level-hint', element);
    const current = () => owner[key]?.tag ?? field.tag;
    const name = (tag) => field.tags.find((option) => option.value === tag)?.label ?? tag;

    choice.dataset.path = path + '.tag';
    label.id ||= 'paneel-' + (++uid);

    if (! config.heading_levels) {
        button.remove();
        list.remove();
        fixed.textContent = name(current());
        fixed.setAttribute('aria-label', format(texts.level_fixed, { level: name(current()) }));
        hint.textContent = format(texts.level_fixed_hint, { level: name(current()) });

        return;
    }

    fixed.remove();
    choice.input = button;
    hint.textContent = path.includes('.') ? hint.dataset.item : hint.dataset.choose;
    list.id = 'paneel-' + (++uid);
    list.setAttribute('aria-labelledby', label.id);
    button.setAttribute('aria-controls', list.id);

    let active = 0;

    const options = field.tags.map((option) => {
        const item = template('data-part="level"');

        item.id = 'paneel-' + (++uid);
        slot('level-label', item).textContent = option.label;
        slot('level-description', item).textContent = option.description;
        // The list keeps the focus, so choosing with the pointer does not close it before the click.
        item.addEventListener('mousedown', (event) => event.preventDefault());
        item.addEventListener('click', () => choose(option.value));

        return item;
    });

    list.replaceChildren(...options);

    const show = () => {
        const tag = current();

        slot('level-name', button).textContent = name(tag);
        button.setAttribute('aria-label', format(texts.level_choose, { level: name(tag) }));
        options.forEach((item, index) => item.setAttribute('aria-selected', String(field.tags[index].value === tag)));
    };

    const mark = (index) => {
        active = index;
        options.forEach((item, position) => item.classList.toggle('actief', position === index));
        list.setAttribute('aria-activedescendant', options[index].id);
        options[index].scrollIntoView({ block: 'nearest' });
    };

    const open = () => {
        list.hidden = false;
        button.setAttribute('aria-expanded', 'true');
        mark(Math.max(0, field.tags.findIndex((option) => option.value === current())));
        list.focus();
    };

    const close = (focus = true) => {
        list.hidden = true;
        button.setAttribute('aria-expanded', 'false');
        list.removeAttribute('aria-activedescendant');

        if (focus) {
            button.focus();
        }
    };

    const choose = (tag) => {
        if (tag !== current()) {
            owner[key] = { text: owner[key]?.text ?? null, tag };
            show();
            follow();
            changed(state);
            leave(state, path + '.tag');
        }

        close();
    };

    // While the list is open the button does not take the focus, so pressing it closes the list instead of reopening it.
    button.addEventListener('mousedown', (event) => {
        if (! list.hidden) {
            event.preventDefault();
        }
    });
    button.addEventListener('click', () => (list.hidden ? open() : close()));
    list.addEventListener('keydown', (event) => {
        const last = options.length - 1;

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            mark(Math.min(last, Math.max(0, active + (event.key === 'ArrowDown' ? 1 : -1))));
        } else if (event.key === 'Home' || event.key === 'End') {
            mark(event.key === 'Home' ? 0 : last);
        } else if (event.key === 'Enter' || event.key === ' ') {
            choose(field.tags[active].value);
        } else if (event.key === 'Escape') {
            close();
        } else {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
    });
    choice.addEventListener('focusout', (event) => {
        if (! list.hidden && ! choice.contains(event.relatedTarget)) {
            close(false);
        }
    });

    show();
}

/* ---------------------------------------------------------------------------------------------------------------
 * Link: one input for a page of the site or an address. Choosing a suggestion, or typing the exact address of a
 * page, links to that page ({page: id}), so the link follows the page when its address changes; anything else is
 * an address ({url}).
 * ------------------------------------------------------------------------------------------------------------ */

function pageOf(id) {
    return pages.find((page) => String(page.id) === String(id)) ?? null;
}

function linkText(value) {
    if (value?.page !== undefined && value?.page !== null) {
        return pageOf(value.page)?.title ?? '';
    }

    return value?.url ?? '';
}

function linkControl(element, input, value, set) {
    const list = slot('options', element);
    const target = slot('target', element);
    let chosen = value ?? null;
    let options = [];
    let active = -1;

    list.id = 'paneel-' + (++uid);
    input.setAttribute('aria-controls', list.id);
    input.value = linkText(chosen);

    const describe = () => {
        const page = chosen?.page !== undefined && chosen?.page !== null ? pageOf(chosen.page) : null;
        const gone = chosen?.page !== undefined && chosen?.page !== null && page === null;

        target.hidden = page === null && ! gone;
        target.classList.toggle('veld-fout', gone);
        target.textContent = gone
            ? texts.page_gone
            : (page ? format(texts.page, { title: page.title, path: page.path ?? '' }) + (page.status === 'published' ? '' : ' · ' + texts.page_draft) : '');
    };

    const choose = (next) => {
        chosen = next;
        set(next);
        describe();
    };

    const close = () => {
        list.hidden = true;
        active = -1;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
    };

    const mark = (index) => {
        active = index;
        [...list.children].forEach((option, position) => option.setAttribute('aria-selected', String(position === index)));

        if (index >= 0) {
            input.setAttribute('aria-activedescendant', list.children[index].id);
            list.children[index].scrollIntoView({ block: 'nearest' });
        }
    };

    const suggest = () => {
        const query = input.value.trim().toLowerCase();

        options = pages
            .filter((page) => query === '' || page.title.toLowerCase().includes(query) || (page.path ?? '').toLowerCase().includes(query))
            .slice(0, 8);

        list.replaceChildren(...options.map((page) => {
            const option = template('data-part="option"');

            option.id = 'paneel-' + (++uid);
            slot('title', option).textContent = page.title;
            slot('path', option).textContent = (page.path ?? '') + (page.status === 'published' ? '' : ' · ' + texts.page_draft);
            option.addEventListener('mousedown', (event) => event.preventDefault());
            option.addEventListener('click', () => pick(page));

            return option;
        }));

        list.hidden = options.length === 0;
        input.setAttribute('aria-expanded', String(! list.hidden));
        active = -1;
    };

    const pick = (page) => {
        input.value = page.title;
        close();
        choose({ page: page.id });
    };

    input.addEventListener('input', () => {
        const text = input.value.trim();
        const page = pages.find((candidate) => candidate.path !== null && candidate.path === text);

        choose(text === '' ? null : (page ? { page: page.id } : { url: text }));
        suggest();
    });
    input.addEventListener('focus', suggest);
    input.addEventListener('blur', close);
    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();

            if (list.hidden) {
                suggest();
            }

            if (options.length > 0) {
                const step = event.key === 'ArrowDown' ? 1 : -1;
                mark((active + step + options.length) % options.length);
            }
        } else if (event.key === 'Enter' && ! list.hidden && active >= 0) {
            event.preventDefault();
            pick(options[active]);
        } else if (event.key === 'Escape' && ! list.hidden) {
            event.preventDefault();
            close();
        }
    });

    describe();
}

/* ---------------------------------------------------------------------------------------------------------------
 * Rich text: a contenteditable with a fixed toolbar. Before a value leaves the panel it is turned into the markup
 * the field keeps (p, strong, em, a, ul, ol, li); the server cleans it again.
 * ------------------------------------------------------------------------------------------------------------ */

const BLOCKS = new Set(['P', 'DIV', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'PRE', 'SECTION', 'ARTICLE', 'HEADER', 'FOOTER', 'TABLE', 'TR', 'FIGURE']);
const DROP = new Set(['SCRIPT', 'STYLE', 'TEMPLATE', 'IFRAME', 'OBJECT', 'EMBED', 'NOSCRIPT']);

function escapeHtml(text) {
    return text.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
}

/*
 * An address a link in rich text may have: http(s), mailto or tel, or an address on the site. It is read as the
 * browser reads it: without the spaces and control characters around it and the tabs and line breaks in it, and with
 * two slashes (or backslashes) at the start as another site.
 */
function safeHref(href) {
    const value = (href ?? '').replace(/^[\u0000- ]+|[\u0000- ]+$/g, '').replace(/[\t\n\r]/g, '');
    const scheme = value.match(/^([a-z][a-z0-9+.-]*):/i)?.[1]?.toLowerCase();

    if (value === '' || /^[\\/]{2}/.test(value)) {
        return null;
    }

    return scheme === undefined || ['http', 'https', 'mailto', 'tel'].includes(scheme) ? value : null;
}

function inline(node) {
    if (node.nodeType === Node.TEXT_NODE) {
        return escapeHtml(node.textContent.replaceAll(' ', ' '));
    }

    if (node.nodeType !== Node.ELEMENT_NODE || DROP.has(node.tagName)) {
        return '';
    }

    if (node.tagName === 'BR') {
        return ' ';
    }

    const content = [...node.childNodes].map(inline).join('');

    if (content.trim() === '') {
        return content;
    }

    switch (node.tagName) {
        case 'STRONG':
        case 'B':
            return '<strong>' + content + '</strong>';
        case 'EM':
        case 'I':
            return '<em>' + content + '</em>';
        case 'A': {
            const href = safeHref(node.getAttribute('href'));

            return href === null ? content : '<a href="' + escapeHtml(href) + '">' + content + '</a>';
        }
        default:
            return content;
    }
}

function richHtml(root) {
    const blocks = [];
    let paragraph = '';

    const flush = () => {
        if (paragraph.trim() !== '') {
            blocks.push('<p>' + paragraph.trim() + '</p>');
        }

        paragraph = '';
    };

    const walk = (parent) => {
        for (const node of parent.childNodes) {
            if (node.nodeType === Node.ELEMENT_NODE && (node.tagName === 'UL' || node.tagName === 'OL')) {
                flush();
                const items = [...node.children]
                    .filter((child) => child.tagName === 'LI')
                    .map((child) => [...child.childNodes].map(inline).join('').trim())
                    .filter((item) => item !== '')
                    .map((item) => '<li>' + item + '</li>');

                if (items.length > 0) {
                    const tag = node.tagName.toLowerCase();
                    blocks.push('<' + tag + '>' + items.join('') + '</' + tag + '>');
                }
            } else if (node.nodeType === Node.ELEMENT_NODE && BLOCKS.has(node.tagName)) {
                flush();
                walk(node);
                flush();
            } else if (node.nodeType === Node.ELEMENT_NODE && node.tagName === 'BR') {
                flush();
            } else {
                paragraph += inline(node);
            }
        }
    };

    walk(root);
    flush();

    return blocks.join('');
}

function richText(element, editor, value, set) {
    const toolbar = slot('toolbar', element);
    const linkBox = slot('link', element);
    const linkInput = slot('link-input', element);
    let range = null;

    slot('link-label', element).htmlFor = linkInput.id = 'paneel-' + (++uid);

    // The value comes from the server already cleaned; it is cleaned once more before it enters the panel.
    editor.innerHTML = richHtml(new DOMParser().parseFromString(value ?? '', 'text/html').body);

    const update = () => {
        const html = richHtml(editor);

        set(html === '' ? null : html);
    };

    const pressed = () => {
        const inside = editor.contains(document.getSelection()?.anchorNode ?? null);

        for (const [command, state] of [['bold', 'bold'], ['italic', 'italic'], ['list', 'insertUnorderedList']]) {
            toolbar.querySelector('[data-command="' + command + '"]').setAttribute('aria-pressed', String(inside && document.queryCommandState(state)));
        }
    };

    const linkAround = () => {
        let node = document.getSelection()?.anchorNode ?? null;

        while (node && node !== editor) {
            if (node.nodeType === Node.ELEMENT_NODE && node.tagName === 'A') {
                return node;
            }

            node = node.parentNode;
        }

        return null;
    };

    const restore = () => {
        editor.focus();

        if (range) {
            const selection = document.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        }
    };

    const command = (name) => {
        editor.focus();
        document.execCommand('defaultParagraphSeparator', false, 'p');

        if (name === 'bold' || name === 'italic') {
            document.execCommand(name);
        } else if (name === 'list') {
            document.execCommand('insertUnorderedList');
        } else if (name === 'link') {
            const selection = document.getSelection();

            range = selection.rangeCount > 0 && editor.contains(selection.anchorNode) ? selection.getRangeAt(0).cloneRange() : null;
            linkInput.value = linkAround()?.getAttribute('href') ?? '';
            linkBox.hidden = false;
            linkInput.focus();

            return;
        }

        update();
        pressed();
    };

    const applyLink = () => {
        const href = safeHref(linkInput.value);

        restore();

        if (href !== null) {
            if (range && ! range.collapsed) {
                document.execCommand('createLink', false, href);
            } else {
                document.execCommand('insertHTML', false, '<a href="' + escapeHtml(href) + '">' + escapeHtml(href) + '</a>');
            }
        }

        linkBox.hidden = true;
        update();
    };

    toolbar.addEventListener('mousedown', (event) => event.preventDefault());
    toolbar.addEventListener('click', (event) => {
        const name = event.target.closest('[data-command]')?.dataset.command;

        if (name) {
            command(name);
        }
    });

    linkBox.addEventListener('click', (event) => {
        const name = event.target.closest('[data-command]')?.dataset.command;

        if (name === 'link-apply') {
            applyLink();
        } else if (name === 'link-remove') {
            restore();
            const link = linkAround();

            if (link) {
                link.replaceWith(...link.childNodes);
            }

            linkBox.hidden = true;
            update();
        } else if (name === 'link-cancel') {
            linkBox.hidden = true;
            restore();
        }
    });

    linkInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            applyLink();
        } else if (event.key === 'Escape') {
            event.preventDefault();
            linkBox.hidden = true;
            restore();
        }
    });

    // Pasting from Word or another site brings only its text; the toolbar adds the markup.
    editor.addEventListener('paste', (event) => {
        event.preventDefault();
        document.execCommand('insertText', false, event.clipboardData?.getData('text/plain') ?? '');
    });
    editor.addEventListener('input', update);
    editor.addEventListener('keyup', pressed);
    editor.addEventListener('mouseup', pressed);
    editor.addEventListener('focus', () => document.execCommand('defaultParagraphSeparator', false, 'p'));
}

/* ---------------------------------------------------------------------------------------------------------------
 * Lists: rows with a grip, a title and "Bewerken". The grip drags with a pointer and moves with the arrow keys.
 * ------------------------------------------------------------------------------------------------------------ */

function summary(field, value) {
    switch (field.type) {
        case 'text':
            return field.emphasis === true ? withoutEmphasis(value ?? '') : (value ?? '');
        case 'long_text':
            return value ?? '';
        case 'rich_text':
            return new DOMParser().parseFromString(value ?? '', 'text/html').body.textContent.trim();
        case 'button':
            return value?.text ?? '';
        case 'heading':
            return field.emphasis === true ? withoutEmphasis(value?.text ?? '') : (value?.text ?? '');
        case 'choice':
            return field.options.find((option) => option.value === value)?.label ?? '';
        case 'link':
            return linkText(value);
        default:
            return '';
    }
}

function itemTitle(field, item, index) {
    const parts = field.fields.map((sub) => summary(sub, item[sub.key])).filter((part) => part !== '');

    return {
        title: parts[0] ?? format(texts.item, { item: field.item_label, n: index + 1 }),
        sub: parts.slice(1).join(' · '),
    };
}

function newItem(field) {
    return Object.fromEntries([['id', null], ...field.fields.map((sub) => [sub.key, clone(sub.default)])]);
}

/* How many photos a list takes from one of its items on: that item and the places left; null without a limit. */
function roomIn(field, items) {
    return field.max === null ? null : 1 + Math.max(0, field.max - items.length);
}

/*
 * What the picker knows of the list of an image in one of its items, so it chooses several photos:
 * {room, max, count, list, items, item}, with the name of the strip, the label of the list and the name of the item
 * ("Fotogalerij", "Foto's", "Foto 2"). A room of 1 is a full list: the picker then chooses one photo. Null outside a
 * list.
 */
function manyFor(state, key, owner) {
    const field = key === null ? null : state.fields.find((candidate) => candidate.key === key);
    const items = field ? state.values[key] : null;

    if (! field || ! Array.isArray(items)) {
        return null;
    }

    return {
        room: roomIn(field, items),
        max: field.max,
        count: items.length,
        list: state.label,
        items: field.label,
        item: format(texts.item, { item: field.item_label, n: items.indexOf(owner) + 1 }),
    };
}

/*
 * A new item for every photo, right after the item that holds the field, with the other fields at their default, as
 * many as the list still takes. The items below move a place, so the errors of the list go (the next save gives them
 * again), and the new items count as finished: a refusal shows on their rows.
 */
function addItems(state, key, item, fieldKey, files) {
    const field = state.fields.find((candidate) => candidate.key === key);
    const list = state.values[key];
    const at = list.indexOf(item);
    const room = roomIn(field, list);
    const fitting = room === null ? files : files.slice(0, room - 1);

    if (at < 0 || fitting.length === 0) {
        return [];
    }

    const items = fitting.map((file) => ({ ...newItem(field), [fieldKey]: file.id }));

    list.splice(at + 1, 0, ...items);
    forgetErrorsOf(state, key);
    state.touched.add(key);
    for (let place = at + 1; place <= at + items.length; place++) {
        state.touched.add('item:' + key + '.' + place);
    }
    liveInput(state, key, field, null);

    return items;
}

/*
 * Says on the line at the foot of the panel what the picker added (design 13.4n): "2 foto's toegevoegd na Foto 2.",
 * the item in the words of the list (its label, the label of an item), with "Ongedaan maken"; marks the items new.
 */
function tellPicked(state, key, item, items) {
    const field = state.fields.find((candidate) => candidate.key === key);
    const after = format(texts.item, { item: field.item_label, n: state.values[key].indexOf(item) + 1 });
    const lower = (text) => String(text ?? '').toLocaleLowerCase();

    items.forEach((added) => fresh.add(added));
    picked = {
        state,
        text: items.length === 1
            ? format(texts.picked_one, { item: lower(field.item_label), after })
            : format(texts.picked_many, { count: items.length, items: lower(field.label), after }),
    };
    showUploads();
}

function items(state, element, field, owner, path) {
    const rows = slot('rows', element);
    const add = slot('add', element);
    const count = slot('count', element);
    const key = field.key;

    if (! Array.isArray(owner[key])) {
        owner[key] = [];
    }

    const list = owner[key];

    const draw = (focus = null) => {
        // A redraw after a save keeps the focus where it was, e.g. on a grip the editor moves with the keyboard.
        const focused = rows.contains(document.activeElement) ? document.activeElement : null;
        const keep = focused ? { index: [...rows.children].indexOf(focused.closest('li')), slot: focused.dataset.slot } : null;

        rows.replaceChildren(...list.map((item, index) => row(state, field, list, index, draw)));

        const limits = field.max !== null
            ? format(texts.count_max, { n: list.length, max: field.max })
            : (field.min !== null ? format(texts.count_min, { n: list.length, min: field.min }) : '');

        count.textContent = limits;
        add.textContent = '+ ' + format(texts.add, { item: field.item_label });
        add.disabled = field.max !== null && list.length >= field.max;

        if (focus !== null) {
            rows.children[focus]?.querySelector('[data-slot="grip"]')?.focus();
        } else if (keep) {
            rows.children[keep.index]?.querySelector('[data-slot="' + keep.slot + '"]')?.focus();
        }
    };

    element.refreshRows = () => draw();

    add.addEventListener('click', () => {
        list.push(newItem(field));
        state.touched.add(path);
        liveInput(state, path, field, null);
        changed(state);
        announce(format(texts.added, { item: field.item_label }));
        openItem(state, key, list.length - 1);
    });

    draw();
}

function row(state, field, list, index, draw) {
    const element = template('data-part="row"');
    const grip = slot('grip', element);
    const edit = slot('edit', element);
    const { title, sub } = itemTitle(field, list[index], index);
    const prefix = field.key + '.' + index;
    const failing = Object.keys(state.errors).some((path) => (path === prefix || path.startsWith(prefix + '.')) && errorShown(state, path));

    slot('title', element).textContent = title;
    slot('fresh', element).hidden = ! fresh.has(list[index]);
    element.classList.toggle('nieuw', fresh.has(list[index]));
    slot('sub', element).textContent = sub;
    slot('sub', element).hidden = sub === '';
    slot('error', element).hidden = ! failing;
    element.classList.toggle('fout', failing);
    grip.setAttribute('aria-label', format(texts.move, { item: title }));
    edit.setAttribute('aria-label', edit.textContent.trim() + ': ' + title);

    const move = (to) => {
        if (to < 0 || to >= list.length || to === index) {
            return;
        }

        const [item] = list.splice(index, 1);
        list.splice(to, 0, item);
        forgetErrorsOf(state, field.key);
        state.touched.add(field.key);
        liveInput(state, field.key, field, null);
        changed(state);
        draw(to);
        announce(format(texts.moved, { item: title, n: to + 1, m: list.length }));
    };

    grip.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
            event.preventDefault();
            move(index + (event.key === 'ArrowUp' ? -1 : 1));
        }
    });
    grip.addEventListener('pointerdown', (event) => drag(event, element, list.length, move));
    edit.addEventListener('click', () => openItem(state, field.key, index));

    return element;
}

/* Dragging moves the row along with the pointer; the item takes its new place when the pointer lets go. */
function drag(event, element, length, move) {
    if (event.button !== 0) {
        return;
    }

    event.preventDefault();
    const rows = [...element.parentElement.children];
    const from = rows.indexOf(element);
    const startY = event.clientY;
    let to = from;

    element.classList.add('sleept');

    const onMove = (moveEvent) => {
        const shift = moveEvent.clientY - startY;
        element.style.transform = 'translateY(' + shift + 'px)';

        const middle = element.getBoundingClientRect().top + element.offsetHeight / 2;
        to = rows.filter((other) => other !== element && other.getBoundingClientRect().top + other.offsetHeight / 2 < middle).length;
        to = Math.max(0, Math.min(length - 1, to));
    };

    const onUp = () => {
        window.removeEventListener('pointermove', onMove);
        window.removeEventListener('pointerup', onUp);
        window.removeEventListener('pointercancel', onUp);
        element.classList.remove('sleept');
        element.style.transform = '';
        move(to);
    };

    window.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', onUp);
    window.addEventListener('pointercancel', onUp);
}

function refreshRows(state) {
    if (state.item) {
        return;
    }

    for (const element of ui.fields.children) {
        element.refreshRows?.();
    }
}

/* ---------------------------------------------------------------------------------------------------------------
 * One item of a list, with the breadcrumb "← Hero / Knoppen / Lid worden" above its fields.
 * ------------------------------------------------------------------------------------------------------------ */

function openItem(state, key, index) {
    state.item = { key, index };
    render();
    ui.itemFields.querySelector('input:not([type="file"]), textarea, select, [contenteditable]')?.focus();
}

function renderItem(state) {
    const field = state.fields.find((candidate) => candidate.key === state.item.key);
    const list = state.values[field.key];
    const index = state.item.index;
    const item = list[index];
    const prefix = field.key + '.' + index;

    ui.stripView.hidden = true;
    ui.saveRow.hidden = true;
    ui.removeStrip.hidden = true;
    ui.itemView.hidden = false;
    ui.crumbStrip.textContent = state.label;
    ui.crumbList.textContent = field.label;
    ui.crumbItem.textContent = itemTitle(field, item, index).title;
    itemPlace(state);
    ui.itemFields.replaceChildren(...field.fields.map((sub) => control(state, sub, item, prefix + '.' + sub.key)));
}

/* The place of the open item in its list ("Item 2 van 5") and whether it can go, also after items were added. */
function itemPlace(state) {
    if (state !== current || ! state.item) {
        return;
    }

    const field = state.fields.find((candidate) => candidate.key === state.item.key);
    const list = state.values[field.key];

    ui.itemPosition.textContent = format(texts.position, { n: state.item.index + 1, m: list.length });
    ui.remove.disabled = field.min !== null && list.length <= field.min;
}

function closeItem(state) {
    const { key, index } = state.item;

    state.touched.add('item:' + key + '.' + index);
    state.item = null;
    render();

    if (state.dirty) {
        save(state);
    }

    const element = ui.fields.querySelector('[data-path="' + key + '"]');
    element?.querySelector('[data-slot="rows"]')?.children[index]?.querySelector('[data-slot="edit"]')?.focus();
}

function removeItem(state) {
    const { key, index } = state.item;
    const field = state.fields.find((candidate) => candidate.key === key);
    const list = state.values[key];
    const { title } = itemTitle(field, list[index], index);

    list.splice(index, 1);
    forgetErrorsOf(state, key);
    state.touched.add(key);
    state.item = null;
    liveInput(state, key, field, null);
    changed(state);
    render();
    announce(format(texts.removed, { item: title }));
    ui.fields.querySelector('[data-path="' + key + '"] [data-slot="add"]')?.focus();
}

/* ---------------------------------------------------------------------------------------------------------------
 * "Ongedaan maken": one step back per click, through the same endpoints as every other change.
 * ------------------------------------------------------------------------------------------------------------ */

function same(a, b) {
    return JSON.stringify(a) === JSON.stringify(b);
}

/* The server stored a change: what it held before becomes a step back, unless this was a step back itself. */
function remember(state, stored, undoing) {
    if (! undoing && ! same(state.stored, stored)) {
        state.history.push(state.stored);
    }

    state.stored = stored;
}

/* Something typed and not stored yet, or a step the server stored before. */
function undoable(state) {
    return state.dirty || state.failed !== null || state.history.length > 0;
}

async function setVisibility(state, visible, undoing = false) {
    const { status, data } = await request('PUT', stripUrl(state.id, '/visibility'), { visible })
        .catch(() => ({ status: 0, data: null }));

    if (status !== 200) {
        if (state === current) {
            ui.visible.checked = state.visible;
        }

        state.failed = status === 401 || status === 419 ? 'expired' : 'failed';
        showStatus(state);

        return false;
    }

    state.visible = data.strip.visible;
    remember(state, { values: state.stored.values, visible: state.visible }, undoing);

    if (state === current) {
        ui.visible.checked = state.visible;
    }

    // Showing or hiding stores no values: the save covers the changes up to the last save of the fields.
    shell.dispatchEvent(new CustomEvent('made-cms:visibility', { detail: { id: state.id, visible: state.visible } }));
    shell.dispatchEvent(new CustomEvent('made-cms:saved', { detail: { id: state.id, seq: state.savedSeq } }));
    showStatus(state);

    return true;
}

async function undo(state) {
    window.clearTimeout(state.timer);
    await state.saving;

    state.item = null;

    // What the editor typed since the last save never reached the server: the fields go back to what it holds.
    if (state.dirty || state.failed !== null) {
        const before = state.values;

        state.values = clone(state.stored.values);
        followChanges(state, before, state.values);
        state.dirty = false;
        state.failed = null;
        state.errors = {};
        render();
        announce(texts.undone);
        // Nothing is stored for this step, so the bar hears that what the strip was refused for is gone.
        shell.dispatchEvent(new CustomEvent('made-cms:reverted', { detail: { id: state.id } }));

        return;
    }

    const step = state.history.pop();

    if (! step) {
        return;
    }

    if (step.visible !== state.stored.visible && ! await setVisibility(state, step.visible, true)) {
        state.history.push(step);

        return;
    }

    if (! same(step.values, state.stored.values)) {
        const before = state.values;

        state.values = clone(step.values);
        followChanges(state, before, state.values);
        state.dirty = true;
        state.undoing = true;
        render();
        await save(state);

        // A step the server refused stays a step: the next click puts the fields back to what it holds.
        if (state.failed !== null) {
            state.history.push(step);

            return;
        }
    }

    if (state === current) {
        render();
    }

    announce(texts.undone);
}

/* ---------------------------------------------------------------------------------------------------------------
 * Publishing and discarding: the bar asks the panel first.
 * ------------------------------------------------------------------------------------------------------------ */

function pending() {
    return [...states.values()].filter((state) => state.dirty || state.saving);
}

/*
 * What the editor left open in the upload fields: an alt text typed for a photo that came in is stored first, as when
 * the editor leaves the field (made-cms:upload-finish). The uploads whose alt text the server refused.
 */
async function finishUploads() {
    const refused = [];

    await Promise.all([...uploads].filter((upload) => ! upload.busy && upload.element.isConnected).map(async (upload) => {
        const field = upload.element.querySelector('[data-upload]');
        const waits = [];

        field?.dispatchEvent(new CustomEvent('made-cms:upload-finish', { detail: { wait: (promise) => waits.push(promise) } }));

        if ((await Promise.all(waits)).includes(false)) {
            refused.push(upload);
        }
    }));

    return refused;
}

/*
 * Saves what is still open in every strip; true once everything the panel holds is stored. The bar can pass report():
 * it hears at once that a photo is still on its way ({uploading: true}) and, when something could not be stored, what
 * failed first ({failed: {id, kind, errors}}): a strip that was refused ('invalid', with the errors of its fields) or
 * could not be reached ('failed', 'expired'), a photo that did not upload ('upload') or an alt text the server
 * refused ('alt'), so the bar can name that strip.
 */
async function flush(report = null) {
    if ([...uploads].some((upload) => upload.busy)) {
        report?.({ uploading: true });
    }

    await landed();

    const refusedAlt = await finishUploads();

    // A strip that could not be reached (no connection, a failing server) is tried again: its values are still the
    // editor's, and without this "try again" would send nothing. A strip the server refused waits for a change.
    for (const state of states.values()) {
        if (state.failed === 'failed' && ! state.dirty && ! state.saving) {
            state.dirty = true;
        }
    }

    for (let waiting = pending(); waiting.length > 0; waiting = pending()) {
        for (const state of waiting) {
            state.showAll = true;
        }

        await Promise.all(waiting.map((state) => save(state)));
    }

    // A photo that did not upload is lost when the editor leaves: it stays in the field with its error until the
    // editor tries again or leaves it out. One the picker superseded landed in Media only and counts for nothing.
    const lost = [...uploads].find((upload) => upload.failed && ! upload.superseded);
    const failed = [...states.values()].find((state) => state.failed);
    const stored = ! unsaved() && ! lost && refusedAlt.length === 0;

    if (! stored) {
        const reason = failed
            ? { id: failed.id, kind: failed.failed, errors: failed.errors ?? {} }
            : (lost
                ? { id: lost.state.id, kind: 'upload', errors: {} }
                : { id: refusedAlt[0].state.id, kind: 'alt', errors: {} });

        report?.({ failed: reason });
    }

    return stored;
}

/*
 * Stops saving and waits for the saves on their way. Once the draft is gone the shell reloads, so the panel forgets
 * what it held and does not warn about it; when throwing it away failed, the panel saves what is still open.
 */
async function discarding(done) {
    paused = true;
    ui.form.inert = true;

    for (const state of states.values()) {
        window.clearTimeout(state.timer);
    }

    await Promise.all([...states.values()].map((state) => state.saving).filter(Boolean));

    done.then((discarded) => {
        if (discarded) {
            states.clear();
            current = null;

            return;
        }

        paused = false;
        ui.form.inert = false;

        for (const state of states.values()) {
            if (state.dirty) {
                changed(state);
            }
        }
    });

    return true;
}

/* ---------------------------------------------------------------------------------------------------------------
 * The opbouw: a strip just added, a strip about to be removed, strips that left the page.
 * ------------------------------------------------------------------------------------------------------------ */

function emptyValue(value) {
    return value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0);
}

/*
 * A strip the opbouw just added says where it went and which required fields are still empty: that is no error yet,
 * but publishing waits for them. The note stays until the editor chooses another strip.
 */
function showAdded(state) {
    if (! state.added) {
        ui.addedNote.hidden = true;

        return;
    }

    const required = state.fields.filter((field) => field.required
        && emptyValue(field.type === 'heading' ? state.values[field.key]?.text : state.values[field.key]));
    const names = new Intl.ListFormat(document.documentElement.lang || 'nl', { type: 'conjunction' })
        .format(required.map((field) => field.label));
    const parts = [state.added.after ? format(texts.new_after, { strip: state.added.after }) : texts.new_top];

    if (required.length === 1) {
        parts.push(format(texts.new_required_one, { fields: names }));
    } else if (required.length > 1) {
        parts.push(format(texts.new_required_many, { count: required.length, fields: names }));
    }

    ui.addedText.textContent = parts.join(' ');
    ui.addedNote.hidden = false;
}

/* Before a strip is removed, what the editor typed in it is saved, so putting it back brings that back too. */
async function settle(id) {
    const state = states.get(id);

    if (! state) {
        return true;
    }

    window.clearTimeout(state.timer);

    if (state.dirty) {
        await save(state);
    }

    await state.saving;

    return true;
}

/* The strips of the page changed: a strip that is gone is forgotten, and when it was open the fields close. */
function forgetGone(list) {
    const ids = new Set(list.map((strip) => strip.id));

    for (const id of states.keys()) {
        if (! ids.has(id)) {
            states.delete(id);
        }
    }

    if (current && ! ids.has(current.id)) {
        close();
    }
}

/* No strip chosen: the panel shows the opbouw instead of fields. */
function close() {
    if (current) {
        current.added = null;
        save(current);
    }

    current = null;
    opening++;
    ui.form.hidden = true;
}

/*
 * edit.js chooses a strip, and the field the editor clicked on the site when there is one. The bar chooses a strip
 * that stopped the publication and hands its errors along; the fields show them. The opbouw chooses a strip it just
 * added, with where it went (added), or no strip at all.
 */
async function select(id, errors, field, count, added = null) {
    // The photo that came in and the items the picker added were told about; choosing a strip moves on.
    if (arrived !== null || picked !== null) {
        arrived = null;
        picked = null;
        showUploads();
    }

    fresh = new WeakSet();

    if (id === null) {
        close();

        return;
    }

    if (current && current.id !== id) {
        current.added = null;
    }

    await open(id);

    if (current?.id !== id) {
        return;
    }

    if (added) {
        current.added = added;
        showAdded(current);
    }

    if (errors) {
        current.errors = errors;
        current.showAll = true;
        current.item = null;
        render();
    } else if (field) {
        focusField(current, field, count);
    }
}

/* The first control of a field, so a rich text gets its text before the buttons of its toolbar. */
function focusIn(element) {
    if (! element) {
        return;
    }

    element.scrollIntoView({ block: 'nearest' });
    (element.focusTarget?.() ?? element.querySelector('input:not([type="file"]), textarea, select, [contenteditable]') ?? element.querySelector('button'))?.focus();
}

/*
 * The editor clicked a field on the site: "kop", a field in an item ("knoppen.1.knop", with the number of items the
 * site shows) or a list ("knoppen"). A field in an item opens that item; when the site shows another number of items
 * than the panel holds, its place in the list says nothing and the list gets the focus.
 */
function focusField(state, path, count) {
    const [key, index, sub] = path.split('.');
    const field = state.fields.find((candidate) => candidate.key === key);

    if (! field) {
        return;
    }

    const list = state.values[key];
    const position = Number(index);
    const inItem = sub !== undefined
        && field.type === 'items'
        && Number.isInteger(position)
        && list?.[position] !== undefined
        && (count === null || count === list.length)
        && field.fields.some((candidate) => candidate.key === sub);

    if (inItem) {
        if (state.item?.key !== key || state.item?.index !== position) {
            if (state.item) {
                closeItem(state);
            }

            openItem(state, key, position);
        }

        focusIn(ui.itemFields.querySelector('[data-path="' + key + '.' + position + '.' + sub + '"]'));

        return;
    }

    if (state.item) {
        closeItem(state);
    }

    focusIn(ui.fields.querySelector('[data-path="' + key + '"]'));
}

/* ---------------------------------------------------------------------------------------------------------------
 * Wiring
 * ------------------------------------------------------------------------------------------------------------ */

if (shell && ui.form) {
    shell.addEventListener('made-cms:select', (event) => select(event.detail.id, event.detail.errors ?? null, event.detail.field ?? null, event.detail.count ?? null, event.detail.added ?? null));
    shell.addEventListener('made-cms:removing', (event) => event.detail.wait(settle(event.detail.id)));
    shell.addEventListener('made-cms:strips', (event) => forgetGone(event.detail.strips ?? []));
    shell.addEventListener('made-cms:flush', (event) => event.detail.wait(flush(event.detail.report)));
    shell.addEventListener('made-cms:discarding', (event) => event.detail.wait(discarding(event.detail.done)));
    shell.addEventListener('made-cms:media-changed', (event) => {
        if (event.detail?.file) {
            mediaChanged(event.detail.file);
        }
    });
    // The editor opened another folder in the picker: the uploads of the panel go there from now on.
    shell.addEventListener('made-cms:media-folder', (event) => {
        folder = event.detail?.folder ?? null;
        uploads.forEach(follow);
    });

    ui.form.addEventListener('submit', (event) => {
        event.preventDefault();

        if (current) {
            current.showAll = true;
            save(current);
            showErrors(current);
            refreshRows(current);
        }
    });

    // While an item is open, the title in the breadcrumb follows what the editor types.
    ui.itemFields.addEventListener('input', () => {
        if (current?.item) {
            const field = current.fields.find((candidate) => candidate.key === current.item.key);
            ui.crumbItem.textContent = itemTitle(field, current.values[field.key][current.item.index], current.item.index).title;
        }
    });

    ui.back.addEventListener('click', () => current?.item && closeItem(current));
    ui.done.addEventListener('click', () => current?.item && closeItem(current));
    ui.remove.addEventListener('click', () => current?.item && removeItem(current));

    ui.visible.addEventListener('change', () => current && setVisibility(current, ui.visible.checked));

    // The line at the foot of the panel: its cross puts a result away, "Open dat veld" goes to a failed upload.
    ui.uploadClose.addEventListener('click', () => {
        if (shown) {
            shown.seen = true;
        } else if (arrived !== null) {
            arrived = null;
        } else {
            picked = null;
        }

        showUploads();
    });
    ui.uploadUndo.addEventListener('click', () => {
        const state = picked?.state;

        picked = null;
        showUploads();

        if (state && states.get(state.id) === state) {
            undo(state);
        }
    });
    ui.uploadOpen.addEventListener('click', () => {
        const spot = shown ? where(shown) : null;

        if (spot) {
            const count = shown.list === null ? null : shown.state.values[shown.list].length;

            shell.dispatchEvent(new CustomEvent('made-cms:choose', { detail: { id: shown.state.id, field: spot.path, count } }));
        }
    });
    ui.undo.addEventListener('click', () => current && undo(current));

    window.addEventListener('beforeunload', (event) => {
        if (unsaved()) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
}
