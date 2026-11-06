/*
 * The opbouw of the edit shell (design 11.6–11.13): every strip of the page in order, adding a strip from the
 * catalogue of the site, moving one (dragging, the arrow keys on its grip, ↑/↓ at the chosen strip) and removing one,
 * with "Ongedaan maken" for as long as the shell is open.
 *
 * The markup and every text come from the server (cms/edit.blade.php and lang/); the list, its counts and the catalogue
 * come from the configuration and from every answer of the endpoints, so this file never counts strips or builds a
 * plural. The place of a strip in the list is its stored position, the number the endpoints take and the number the
 * panel shows ("Strook 2 van 6"), also for hidden strips and strips of a type the site no longer has.
 *
 * Every change goes to the other scripts as made-cms:strips: edit.js takes the new labels and places and reloads the
 * frame, bar.js asks for the state of the draft, panel.js forgets a strip that left the page. Before removing a strip
 * the panel saves what it holds of it (made-cms:removing). Choosing a strip goes through edit.js (made-cms:choose),
 * which tells everyone (made-cms:select); no strip (null) shows the opbouw.
 */

const SEARCH_FROM = 10;

const shell = document.querySelector('[data-edit-shell]');
const config = JSON.parse(document.querySelector('script[data-config]')?.textContent ?? '{}');
const texts = config.texts?.structure ?? {};
const shellTexts = config.texts ?? {};
const types = config.types ?? [];

const slot = (name, root = shell) => root?.querySelector('[data-slot="' + name + '"]');

const ui = {
    eyebrow: slot('head-eyebrow'),
    name: slot('head-name'),
    sub: slot('head-sub'),
    position: slot('head-position'),
    structure: slot('structure'),
    notices: slot('structure-notices'),
    empty: slot('structure-empty'),
    first: slot('structure-first'),
    filled: slot('structure-filled'),
    list: slot('structure-list'),
    add: slot('structure-add'),
    status: slot('structure-status'),
    catalogue: slot('catalogue'),
    catalogueBack: slot('catalogue-back'),
    place: slot('catalogue-place'),
    other: slot('catalogue-other'),
    searchField: slot('catalogue-search-field'),
    search: slot('catalogue-search'),
    types: slot('catalogue-types'),
    none: slot('catalogue-none'),
    cancel: slot('catalogue-cancel'),
    catalogueStatus: slot('catalogue-status'),
    back: slot('structure-back'),
    crumb: slot('structure-crumb'),
    up: slot('structure-up'),
    down: slot('structure-down'),
    moveStatus: slot('structure-move-status'),
    remove: slot('structure-remove-strip'),
    announce: slot('announce'),
};

const structureEyebrow = ui.eyebrow?.textContent ?? '';

let strips = config.strips ?? [];
let counts = config.counts ?? '';
let view = 'structure';
let selected = null;
let previous = null;

// The strip the opbouw added last: its head says "Nieuwe strook" until another strip is chosen.
let added = null;

// Where a strip from the catalogue goes.
let insertAt = 0;

// One change at a time: the answer of one holds the list the next one counts on.
let busy = false;

// Removed strips as the server stored them, the newest last: "Ongedaan maken" puts one back.
const removed = [];

// A strip moved with the arrow keys: the order it shows until Enter, leaving the grip or Escape.
let lifted = null;
let redrawing = false;
let asking = 0;

/* Fills :placeholders like Laravel does: the longest name first, so :id never eats into :identifier. */
function format(text, replace = {}) {
    return Object.keys(replace)
        .sort((a, b) => b.length - a.length)
        .reduce((result, key) => result.replaceAll(':' + key, String(replace[key])), text ?? '');
}

function template(part) {
    return document.querySelector('template[data-part="' + part + '"]').content.firstElementChild.cloneNode(true);
}

function announce(text) {
    ui.announce.textContent = '';
    window.setTimeout(() => {
        ui.announce.textContent = text;
    }, 50);
}

function indexOf(id) {
    return strips.findIndex((strip) => strip.id === id);
}

function stripOf(id) {
    return strips.find((strip) => strip.id === id) ?? null;
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

function failure() {
    return { status: 0, data: null };
}

/* What went wrong, for the status line: the message of a refusal, else the connection or the session. */
function problem(status, data) {
    if (status === 422) {
        const messages = Object.values(data?.errors ?? {});

        return messages[0]?.[0] ?? texts.failed;
    }

    return status === 419 ? texts.expired : texts.failed;
}

/* Hands a promise to wait() for every listener, like bar.js does, and waits for all of them. */
async function ask(type, detail = {}) {
    const waits = [];

    shell.dispatchEvent(new CustomEvent(type, { detail: { ...detail, wait: (promise) => waits.push(promise) } }));

    await Promise.all(waits);
}

/* The list as the server answered it, for every script of the shell. */
function update(data, { reload = false, reveal = null } = {}) {
    strips = data.strips ?? strips;
    counts = data.counts ?? counts;
    shell.dispatchEvent(new CustomEvent('made-cms:strips', { detail: { strips, counts, reload, reveal } }));
    render();
}

/* The summaries and counts follow what the panel saved: the opbouw asks for them again when it shows. */
async function refresh() {
    const ticket = ++asking;
    const { status, data } = await request('GET', config.urls?.strips).catch(failure);

    if (ticket === asking && status === 200 && ! busy && lifted === null) {
        update(data);
    }
}

/* ---------------------------------------------------------------------------------------------------------------
 * The head and the views: the opbouw, the catalogue, or the fields of the chosen strip (panel.js)
 * ------------------------------------------------------------------------------------------------------------ */

function renderHead() {
    ui.position.hidden = view === 'catalogue';

    if (view === 'strip') {
        const strip = stripOf(selected);
        const index = indexOf(selected);

        ui.eyebrow.textContent = selected === added ? texts.eyebrow_new : texts.eyebrow_strip;
        ui.name.textContent = strip?.label ?? shellTexts.unknown ?? '';
        ui.sub.textContent = texts.sub_strip ?? '';
        ui.position.textContent = index < 0 ? '' : format(shellTexts.position, { n: index + 1, m: strips.length })
            + (strip?.visible === false ? ' · ' + shellTexts.hidden : '');

        return;
    }

    ui.eyebrow.textContent = structureEyebrow;
    ui.name.textContent = config.page?.title ?? '';
    ui.position.textContent = texts.page ?? '';

    if (view === 'catalogue') {
        ui.sub.textContent = texts.sub_catalogue ?? '';
    } else {
        ui.sub.textContent = strips.length === 0 ? counts + '.' : counts + ' · ' + texts.sub_structure;
    }
}

function showView(next) {
    view = next;
    ui.structure.hidden = view !== 'structure';
    ui.catalogue.hidden = view !== 'catalogue';
    renderHead();
}

function render() {
    renderList();
    renderHead();

    if (view === 'strip') {
        renderStripNav();
    }
}

/* ---------------------------------------------------------------------------------------------------------------
 * The list
 * ------------------------------------------------------------------------------------------------------------ */

function renderList() {
    const shown = lifted?.order ?? strips;
    const items = [];

    ui.empty.hidden = shown.length > 0;
    ui.filled.hidden = shown.length === 0;

    shown.forEach((strip, index) => {
        items.push(insertPoint(strip, index));
        items.push(strip.known === false ? unknownRow(strip) : row(strip, index, shown.length));

        if (strip.known === false) {
            const note = template('unknown-note');

            note.textContent = format(texts.unknown_note, { type: strip.type });
            items.push(note);
        }
    });

    ui.list.replaceChildren(...items);
}

function insertPoint(strip, index) {
    const item = template('insert');
    const button = slot('row-insert', item);

    button.setAttribute('aria-label', format(texts.insert_before, { strip: strip.label }));
    button.addEventListener('click', () => openCatalogue(index));

    return item;
}

function row(strip, index, total) {
    const item = template('strip-row');
    const grip = slot('row-grip', item);
    const edit = slot('row-edit', item);

    item.dataset.strip = strip.id;
    item.classList.toggle('tilt', lifted?.id === strip.id);
    slot('row-name', item).textContent = strip.label;
    slot('row-hidden', item).hidden = strip.visible !== false;
    slot('row-sub', item).textContent = (strip.summary ?? texts.empty_strip) + ' · ' + strip.fields;
    grip.setAttribute('aria-label', format(texts.grip, { strip: strip.label, n: index + 1, m: total }));
    edit.setAttribute('aria-label', edit.textContent.trim() + ': ' + strip.label);

    grip.addEventListener('pointerdown', (event) => drag(event, item, strip));
    grip.addEventListener('keydown', (event) => keyMove(event, strip));
    grip.addEventListener('blur', () => {
        if (lifted !== null && ! redrawing) {
            drop();
        }
    });
    edit.addEventListener('click', () => choose(strip.id));

    return item;
}

/* A strip of a type the site no longer has: its content is kept, it cannot be chosen or moved, only removed. */
function unknownRow(strip) {
    const item = template('unknown-row');
    const remove = slot('row-remove', item);

    item.dataset.strip = strip.id;
    slot('row-name', item).textContent = strip.label;
    slot('row-sub', item).textContent = texts.unknown_summary ?? '';
    remove.setAttribute('aria-label', remove.textContent.trim() + ': ' + strip.label);
    remove.addEventListener('click', () => removeStrip(strip.id));

    return item;
}

/* The focus on a part of the row of a strip, or on "+ Strook toevoegen" when that row is gone. */
function focusRow(id, part = 'row-grip') {
    const item = ui.list.querySelector('[data-strip="' + CSS.escape(id) + '"]');
    const target = item === null ? null : (slot(part, item) ?? slot('row-edit', item) ?? slot('row-remove', item));

    (target ?? ui.add).focus();
}

/* The status line of the view the editor is in. */
function say(text) {
    const target = view === 'catalogue' ? ui.catalogueStatus : (view === 'strip' ? ui.moveStatus : ui.status);

    for (const element of [ui.status, ui.catalogueStatus, ui.moveStatus]) {
        element.textContent = element === target ? (text ?? '') : '';
    }
}

/* ---------------------------------------------------------------------------------------------------------------
 * Moving: one request per drop, because every move reloads the frame.
 * ------------------------------------------------------------------------------------------------------------ */

async function move(id, to) {
    const from = indexOf(id);
    const strip = stripOf(id);

    if (busy || strip === null || to === from || to < 0 || to >= strips.length) {
        return;
    }

    busy = true;
    const { status, data } = await request('PUT', stripUrl(id, '/position'), { position: to }).catch(failure);
    busy = false;

    if (status !== 200) {
        say(problem(status, data));
        render();

        if (status === 422) {
            refresh();
        }

        return;
    }

    update(data, { reload: true, reveal: id });
    say(format(texts.moved, { strip: strip.label, n: to + 1, m: strips.length }));
}

/*
 * Dragging takes the row out of the list and leaves a place of the same height that follows it; the strip takes
 * that place when the pointer lets go.
 */
function drag(event, item, strip) {
    if (event.button !== 0 || busy || lifted !== null) {
        return;
    }

    event.preventDefault();

    const from = indexOf(strip.id);
    const box = item.getBoundingClientRect();
    const top = box.top - ui.list.getBoundingClientRect().top;
    const startY = event.clientY;
    const place = template('placeholder');

    place.textContent = format(texts.placeholder, { strip: strip.label });
    place.style.height = box.height + 'px';
    item.before(place);
    item.classList.add('tilt', 'sleept');
    item.style.top = top + 'px';
    item.style.width = box.width + 'px';

    const others = () => [...ui.list.querySelectorAll('.strookrij')].filter((other) => other !== item);

    const onMove = (moveEvent) => {
        item.style.top = (top + moveEvent.clientY - startY) + 'px';

        const middle = item.getBoundingClientRect().top + box.height / 2;
        const next = others().find((other) => {
            const otherBox = other.getBoundingClientRect();

            return middle < otherBox.top + otherBox.height / 2;
        });

        if (! next) {
            ui.list.append(place);

            return;
        }

        const anchor = next.previousElementSibling?.classList.contains('invoeg') ? next.previousElementSibling : next;

        if (place.nextElementSibling !== anchor) {
            anchor.before(place);
        }
    };

    const onUp = () => {
        window.removeEventListener('pointermove', onMove);
        window.removeEventListener('pointerup', onUp);
        window.removeEventListener('pointercancel', onUp);

        const to = [...ui.list.children]
            .filter((child) => child === place || (child.classList.contains('strookrij') && child !== item))
            .indexOf(place);

        renderList();

        if (to !== from) {
            move(strip.id, to).then(() => focusRow(strip.id));
        }
    };

    window.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', onUp);
    window.addEventListener('pointercancel', onUp);
}

/* The list again with the focus on the grip of a strip; leaving the old grip while doing so is no drop. */
function redraw(id) {
    redrawing = true;
    renderList();
    focusRow(id);
    redrawing = false;
}

/* ↑ and ↓ on a grip lift the strip and move it in the list; Enter (or leaving the grip) drops it, Escape puts it back. */
function keyMove(event, strip) {
    if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
        event.preventDefault();

        if (busy) {
            return;
        }

        lifted ??= { id: strip.id, order: strips.slice() };

        const from = lifted.order.findIndex((candidate) => candidate.id === strip.id);
        const to = from + (event.key === 'ArrowUp' ? -1 : 1);

        if (to < 0 || to >= lifted.order.length) {
            return;
        }

        lifted.order.splice(from, 1);
        lifted.order.splice(to, 0, strip);
        redraw(strip.id);
    } else if ((event.key === 'Enter' || event.key === ' ') && lifted !== null) {
        event.preventDefault();
        drop();
    } else if (event.key === 'Escape' && lifted !== null) {
        event.preventDefault();
        lifted = null;
        redraw(strip.id);
    }
}

function drop() {
    const { id, order } = lifted;
    const to = order.findIndex((strip) => strip.id === id);

    lifted = null;
    redraw(id);
    move(id, to).then(() => focusRow(id));
}

/* ---------------------------------------------------------------------------------------------------------------
 * The chosen strip: the way back to the opbouw, ↑/↓ and removing it.
 * ------------------------------------------------------------------------------------------------------------ */

function renderStripNav() {
    const index = indexOf(selected);
    const strip = stripOf(selected);

    ui.crumb.textContent = strip?.label ?? '';
    ui.up.setAttribute('aria-label', format(texts.up, { n: Math.max(index, 1) }));
    ui.down.setAttribute('aria-label', format(texts.down, { n: Math.min(index + 2, strips.length) }));
    ui.up.setAttribute('aria-disabled', String(index <= 0));
    ui.down.setAttribute('aria-disabled', String(index < 0 || index >= strips.length - 1));
}

function choose(id) {
    shell.dispatchEvent(new CustomEvent('made-cms:choose', { detail: { id } }));
}

/* ---------------------------------------------------------------------------------------------------------------
 * Removing and putting back
 * ------------------------------------------------------------------------------------------------------------ */

function renderNotices() {
    ui.notices.replaceChildren(...removed.slice().reverse().map((entry) => {
        const notice = template('notice');
        const undo = slot('notice-undo', notice);
        const text = format(texts.removed, { strip: entry.label });

        slot('notice-text', notice).textContent = text;
        undo.setAttribute('aria-label', undo.textContent.trim() + ': ' + text);
        undo.addEventListener('click', () => restore(entry));
        notice.dataset.strip = entry.strip.id;

        return notice;
    }));
}

/* The panel first saves what it holds of the strip, so "Ongedaan maken" brings back what the editor typed too. */
async function removeStrip(id) {
    const index = indexOf(id);
    const strip = stripOf(id);

    if (busy || strip === null) {
        return;
    }

    busy = true;
    await ask('made-cms:removing', { id });

    const { status, data } = await request('DELETE', stripUrl(id)).catch(failure);
    busy = false;

    if (status !== 200) {
        say(problem(status, data));

        return;
    }

    const wasChosen = selected === id;

    removed.push({ strip: data.removed, position: index, label: strip.label });
    renderNotices();
    update(data, { reload: true });

    if (wasChosen) {
        choose(null);
    }

    say(format(wasChosen ? texts.removed_hint : texts.removed, { strip: strip.label }));
    ui.notices.querySelector('[data-slot="notice-undo"]')?.focus();
}

async function restore(entry) {
    if (busy) {
        return;
    }

    const position = Math.min(entry.position, strips.length);

    busy = true;
    const { status, data } = await request('POST', stripUrl(entry.strip.id, '/restore'), {
        type: entry.strip.type,
        visible: entry.strip.visible,
        values: entry.strip.values,
        position,
    }).catch(failure);
    busy = false;

    if (status !== 201) {
        say(problem(status, data));

        return;
    }

    removed.splice(removed.indexOf(entry), 1);
    renderNotices();
    update(data, { reload: true, reveal: entry.strip.id });
    say(format(texts.restored, { strip: entry.label, n: position + 1 }));
    focusRow(entry.strip.id, 'row-edit');
}

/* ---------------------------------------------------------------------------------------------------------------
 * Adding: the catalogue of the site, one click places a strip of a type
 * ------------------------------------------------------------------------------------------------------------ */

function openCatalogue(position) {
    insertAt = position;
    say('');
    showView('catalogue');

    const after = position > 0 ? strips[position - 1]?.label : texts.top;

    ui.place.textContent = format(texts.comes_after, { strip: after ?? texts.top, n: position + 1, m: strips.length + 1 });
    ui.searchField.hidden = types.length < SEARCH_FROM;
    ui.search.value = '';
    renderTypes();
    (ui.searchField.hidden ? ui.types.querySelector('button') : ui.search)?.focus();
}

function renderTypes() {
    const query = ui.search.value.trim().toLocaleLowerCase();
    const matching = types.filter((type) => query === ''
        || type.label.toLocaleLowerCase().includes(query)
        || (type.description ?? '').toLocaleLowerCase().includes(query));

    ui.types.replaceChildren(...matching.map((type) => {
        const card = template('type');
        const description = slot('type-description', card);

        slot('type-name', card).textContent = type.label;
        slot('type-fields', card).textContent = type.fields;
        description.textContent = type.description ?? '';
        description.hidden = ! type.description;
        card.addEventListener('click', () => add(type));

        return card;
    }));
    ui.none.hidden = matching.length > 0;
}

function backToStructure() {
    showView('structure');
    ui.add.focus();
}

async function add(type) {
    if (busy) {
        return;
    }

    const position = insertAt;
    const after = position > 0 ? (strips[position - 1]?.label ?? null) : null;

    busy = true;
    const { status, data } = await request('POST', config.urls?.strips, { type: type.key, position }).catch(failure);
    busy = false;

    if (status !== 201) {
        say(problem(status, data));

        return;
    }

    const id = data.strip.id;

    added = id;
    update(data, { reload: true, reveal: id });
    shell.dispatchEvent(new CustomEvent('made-cms:choose', { detail: { id, added: { after } } }));
    announce(format(texts.added, { strip: type.label, n: position + 1, m: strips.length }));
}

/* ---------------------------------------------------------------------------------------------------------------
 * Wiring
 * ------------------------------------------------------------------------------------------------------------ */

if (shell && ui.list && ui.catalogue) {
    shell.addEventListener('made-cms:select', (event) => {
        const id = event.detail.id ?? null;

        lifted = null;

        if (id === null) {
            selected = null;
            showView('structure');
            renderList();

            if (previous !== null) {
                focusRow(previous, 'row-edit');
            }

            refresh();

            return;
        }

        if (id !== added) {
            added = null;
        }

        if (id !== selected) {
            ui.moveStatus.textContent = '';
        }

        previous = id;
        selected = id;
        showView('strip');
        renderStripNav();
    });

    // Showing or hiding a strip changes its label in the opbouw and the counts.
    shell.addEventListener('made-cms:visibility', (event) => {
        const strip = stripOf(event.detail.id);

        if (strip) {
            strip.visible = event.detail.visible;
        }

        render();
        refresh();
    });

    // A published draft holds no removed strips any more to put back.
    shell.addEventListener('made-cms:published', () => {
        removed.length = 0;
        renderNotices();
    });

    ui.add.addEventListener('click', () => openCatalogue(strips.length));
    ui.first.addEventListener('click', () => openCatalogue(0));
    ui.catalogueBack.addEventListener('click', backToStructure);
    ui.other.addEventListener('click', backToStructure);
    ui.cancel.addEventListener('click', backToStructure);
    ui.search.addEventListener('input', renderTypes);
    ui.back.addEventListener('click', () => choose(null));

    for (const [button, step] of [[ui.up, -1], [ui.down, 1]]) {
        button.addEventListener('click', () => {
            if (button.getAttribute('aria-disabled') !== 'true' && selected !== null) {
                move(selected, indexOf(selected) + step);
            }
        });
    }

    ui.remove.addEventListener('click', () => selected !== null && removeStrip(selected));

    renderNotices();
    renderList();
    showView('structure');
}
