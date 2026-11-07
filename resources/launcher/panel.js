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
 */

const SAVE_AFTER = 700;

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
};

// Every strip the editor opened keeps its state, so going back to it loses nothing that was not saved yet.
const states = new Map();
let pages = [];
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
            ui.status.textContent = status === 419 ? texts.expired : texts.load_failed;

            return;
        }

        pages = data.pages ?? pages;
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
        case 'items':
            return null;
        case 'rich_text': {
            // edit.js puts rich text into the site as HTML, so it only ever gets the markup the panel builds itself.
            const html = typeof value === 'string' ? richHtml(new DOMParser().parseFromString(value, 'text/html').body) : '';

            return html === '' ? null : html;
        }
        default:
            return value ?? null;
    }
}

/*
 * One field of the strip changed: "kop", or "knoppen.1.knop" in an item, with the number of items so edit.js knows
 * the site shows the same list. A list itself ("knoppen") changes when an item is added, removed or moved.
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
            if (status === 200) {
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
                state.failed = status === 419 ? 'expired' : 'failed';
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
    const error = element.querySelector(':scope > [data-slot="error"]');
    const ids = [];

    for (const part of [hint, error]) {
        if (part) {
            part.id = 'paneel-' + (++uid);
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
        case 'items':
            items(state, element, field, owner, path);
            break;
    }

    describedBy(element, input);
    element.addEventListener('focusout', (event) => {
        if (! element.contains(event.relatedTarget) && field.type !== 'items' && field.type !== 'button') {
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
        case 'long_text':
            return value ?? '';
        case 'rich_text':
            return new DOMParser().parseFromString(value ?? '', 'text/html').body.textContent.trim();
        case 'button':
            return value?.text ?? '';
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
    ui.itemFields.querySelector('input, textarea, select, [contenteditable]')?.focus();
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
    ui.itemPosition.textContent = format(texts.position, { n: index + 1, m: list.length });
    ui.remove.disabled = field.min !== null && list.length <= field.min;
    ui.itemFields.replaceChildren(...field.fields.map((sub) => control(state, sub, item, prefix + '.' + sub.key)));
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

        state.failed = status === 419 ? 'expired' : 'failed';
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

/* Saves what is still open in every strip; true once everything the panel holds is stored. */
async function flush() {
    for (let waiting = pending(); waiting.length > 0; waiting = pending()) {
        for (const state of waiting) {
            state.showAll = true;
        }

        await Promise.all(waiting.map((state) => save(state)));
    }

    return ! unsaved();
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

    const required = state.fields.filter((field) => field.required && emptyValue(state.values[field.key]));
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
    (element.querySelector('input, textarea, select, [contenteditable]') ?? element.querySelector('button'))?.focus();
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
    shell.addEventListener('made-cms:flush', (event) => event.detail.wait(flush()));
    shell.addEventListener('made-cms:discarding', (event) => event.detail.wait(discarding(event.detail.done)));

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
    ui.undo.addEventListener('click', () => current && undo(current));

    window.addEventListener('beforeunload', (event) => {
        if (unsaved()) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
}
