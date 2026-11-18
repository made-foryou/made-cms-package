/*
 * Made CMS: the list of the media library and the picker of an image (design 13.4, 14.4).
 *
 * Every [data-media] is a list: the folders with the number of their files (made-cms/media/folders), the files a page
 * at a time as tiles (made-cms/media/files), a search, the filters and "Meer tonen". Its fixed texts and the templates
 * of a folder and a tile are in its markup (cms/partials/media-browser.blade.php), shared with the media library; its
 * config is JSON on itself (data-config). A tile is a button with aria-pressed: the file it chooses. Opening a folder
 * sends the upload field into it; the picker remembers it for the session once its files are in, and a file uploaded
 * in the picker is the choice at once.
 *
 * The list of the media library (config.library, design 13.1) shows every kind: one group of filters with the numbers
 * of the whole library (every kind, a kind, or the images without an alt text), the folders as chips counting what
 * the filter and the search show, a trail, tiles or rows of a list (remembered by the browser), and what an empty list
 * offers. Its first page and folders come with the page (config.initial), so it shows them without a request, and its
 * address follows what it lists and the chosen file. A new file joins the list where its filter, search and order place
 * it (they stay), without being chosen, and a file that changed elsewhere on the page (made-cms:media-changed) shows
 * it in its tile.
 *
 * The library also manages its folders (design 13.1aa–az): "Nieuwe map" ([data-library-new-folder]) and "Hernoemen"
 * open one field below the chips, "Verwijderen" deletes the open folder when it is empty, and what they did shows in
 * one message below it until the editor closes it, the next one comes, or another folder or filter. "Selecteren" (and
 * Cmd/Ctrl- or Shift-click on a tile) selects files instead of choosing one; the selection belongs to the list the
 * editor sees. A tile dragged onto a folder chip, or onto "Alle bestanden" in the trail, moves it there, or the whole
 * selection when it is selected. A drag of a tile carries only DRAG_TYPE, so the layer for files from the computer
 * never shows for it. Firefox drags no button: there the folder of the side column moves files.
 *
 * What a host hears on the list (the events bubble):
 * - made-cms:media-choosing {file, wait(promise)} before the choice changes, cancelable: a host that cancels it keeps
 *   the choice, and one that hands wait() a promise holds it until the promise resolved (false keeps it);
 * - made-cms:media-chosen {file} when the choice changes, the file as the upload field gets one;
 * - made-cms:media-folder {folder} when the editor opens a folder: {id, name}, or null for "Alle bestanden";
 * - made-cms:media-folders {folders} in the library, every time its folders load: [{id, name}] from a to z;
 * - made-cms:media-selection {selecting, files, full} in the library and in a picker opened from an item of a list,
 *   when it starts or stops selecting and when the selection changes (full: the list of the item takes no more);
 * - made-cms:media-changed {file} in the library for a file it moved that stays chosen.
 * A host loads the list with made-cms:media-load {file, many} on it: the folder it was in, no search, newest first,
 * with that file chosen and nothing selected; many ({room, max, count, list, items, item}) for a picker opened from an
 * item of a list, null outside one; made-cms:media-deselect {id} takes a file out of its selection. A list outside a
 * dialog loads at once. In the library a host stops selecting with
 * made-cms:media-select {on: false}, and moves files with made-cms:media-move {files, folder, done(result)}: the ids,
 * the folder ({id, name}, null for "Alle bestanden"), and done gets {ok, error}; the list sets detail.taken.
 *
 * The picker ([data-media-picker]) is a modal <dialog> around a list. The side panel opens it with made-cms:pick on the
 * dialog, {field, place, hint, file, many, opener, choose(files)}; "Deze gebruiken" closes it and calls choose with
 * the chosen file, or, opened for an item of a list (many), with the files it selects, in the order of the items they
 * become. Escape, "Annuleren", the cross and a click beside it close it without a choice; the focus goes back to
 * opener. Both finish the upload fields first, so an alt text the editor typed for a new photo is stored and goes with
 * the choice, and the fields are at rest the next time the picker opens. The picker holds the upload field of one
 * photo and of several; each time it opens it shows the field of several photos when it chooses several files.
 */

const HEADERS = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

/** How long the editor pauses typing before the list searches, in ms. */
const SEARCH_AFTER = 250;

/** What a tile dragged in the library carries: the ids of the files, as JSON. Never "Files", the upload layer's. */
const DRAG_TYPE = 'application/x-made-cms-media';

/** The first message of a refusal, else null. */
function first(messages) {
    return Array.isArray(messages) && messages.length > 0 ? String(messages[0]) : null;
}

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

const pressed = (button) => button.getAttribute('aria-pressed') === 'true';

/**
 * Finishes the upload field of a list before the list is left (made-cms:upload-finish): a typed alt text is stored,
 * without one it is "later". True unless the field kept an alt text the server refused.
 */
async function finished(upload) {
    if (! upload) {
        return true;
    }

    const waits = [];

    upload.dispatchEvent(new CustomEvent('made-cms:upload-finish', { detail: { wait: (promise) => waits.push(promise) } }));

    return (await Promise.all(waits)).every((result) => result !== false);
}

function browse(root) {
    const settings = configOf(root);

    if (! settings) {
        return;
    }

    const library = settings.library === true;
    const texts = settings.texts ?? {};
    const slot = (name, scope = root) => scope.querySelector('[data-slot="media-' + name + '"]');
    const ui = {
        folders: slot('folders'),
        search: slot('search'),
        newest: slot('newest'),
        withoutAlt: slot('without-alt'),
        status: slot('status'),
        grid: slot('grid'),
        empty: slot('empty'),
        failed: slot('failed'),
        more: slot('more'),
    };
    // The parts only the library has: its filters with their numbers, the trail, the views, the note and its start.
    const shelf = library ? {
        kinds: slot('kinds'),
        withoutAltCount: slot('without-alt-count'),
        trailAll: slot('trail-all'),
        trailSeparator: slot('trail-separator'),
        trailHere: slot('trail-here'),
        tiles: slot('view-tiles'),
        list: slot('view-list'),
        note: slot('note'),
        start: slot('start'),
        upload: slot('upload'),
        emptyTitle: slot('empty-title'),
        emptyText: slot('empty-text'),
        emptyUpload: slot('empty-upload'),
        emptyLink: slot('empty-link'),
        folderField: slot('folder-field'),
        folderLabel: slot('folder-label'),
        folderInput: slot('folder-input'),
        folderConfirm: slot('folder-confirm'),
        folderCancel: slot('folder-cancel'),
        folderHint: slot('folder-hint'),
        folderActions: slot('folder-actions'),
        rename: slot('rename'),
        remove: slot('delete'),
        notice: slot('notice'),
        select: slot('select'),
    } : null;
    // The parts of a picker that chooses several photos for an item of a list (design 13.4b–p): the line with the
    // button, the messages above the tiles and the hint below them. Only the picker has them.
    const stand = {
        root: slot('picker-stand'),
        status: slot('picker-status'),
        full: slot('picker-full'),
        many: slot('picker-many'),
        notices: slot('picker-notices'),
        hint: slot('picker-hint'),
        notice: root.querySelector('template[data-picker-notice]'),
    };
    const folderTemplate = root.querySelector('template[data-media-folder]');
    const tileTemplate = root.querySelector('template[data-media-tile]');
    const noticeTemplate = root.querySelector('template[data-media-notice]');
    const all = ui.folders?.querySelector('button[data-folder=""]');
    // The upload fields of the list. The picker holds two ([data-picker-upload]): the field of one photo and the field
    // of several photos for a picker opened from an item of a list; each time it opens one of them is in sight, so a
    // field never changes while its files are on their way. Every field follows the open folder; the list hears only
    // the field in sight.
    const uploads = [...root.querySelectorAll('[data-upload]')];
    let upload = uploads[0] ?? null;

    if (Object.values(ui).some((element) => element === null) || ! folderTemplate || ! tileTemplate || ! all) {
        return;
    }

    if (shelf && (Object.values(shelf).some((element) => element === null) || ! noticeTemplate)) {
        return;
    }

    // "Nieuwe map" sits in the head of the screen, next to "Uploaden".
    const newFolder = library ? document.querySelector('[data-library-new-folder]') : null;

    const filter = settings.filter ?? {};
    const state = {
        // The open folder, {id, name}; null for "Alle bestanden".
        folder: settings.folder ?? null,
        // The folder the session holds, so it is only sent when it changes.
        remembered: settings.folder?.id ?? null,
        // The kind the library lists, null for every kind; the picker has a fixed kind of its own (settings.kind).
        kind: filter.kind ?? null,
        page: 1,
        files: [],
        total: 0,
        chosen: null,
        // Counts the choices of other files: a choice that waits for a host goes on only while no newer one came.
        turn: 0,
        loading: null,
        uploading: false,
        empty: false,
        // The library: the folders as they loaded last, [{id, name, count}].
        folderList: [],
        // Selecting instead of choosing, the selected files by id in the order they were selected, and the file a
        // Shift-click selects a range from.
        selecting: false,
        selected: new Map(),
        anchor: null,
        // What the list on the page answers (folder, filter, search, order), so loading it again keeps the selection.
        listed: null,
        // The picker opened from an item of a list: {room, max, count, list, items, item}, room the number of files the
        // list takes from that item on (null for no limit; 1 for a full list, which chooses one file); null outside a
        // list.
        many: null,
        // In that picker: more than one photo uploaded at once started selecting, the photos that came in while the
        // list took no more, and the photos uploaded since it opened (they say they are new).
        started: false,
        over: 0,
        fresh: new Set(),
        // The field below the chips: {mode: 'new' | 'rename', busy}, or null while it is closed.
        field: null,
        // The ids of the files a drag carries.
        dragging: null,
    };
    let typing = null;
    let refreshing = null;

    function tell(name, detail) {
        root.dispatchEvent(new CustomEvent(name, { bubbles: true, detail }));
    }

    function url(address, query) {
        const target = new URL(address, window.location.href);

        for (const [key, value] of Object.entries(query)) {
            if (value !== null && value !== undefined && value !== '') {
                target.searchParams.set(key, String(value));
            }
        }

        return target;
    }

    const kind = () => settings.kind ?? state.kind;
    const search = () => ui.search.value.trim();
    const withoutAlt = () => pressed(ui.withoutAlt);

    function pressFolders() {
        const open = String(state.folder?.id ?? '');

        ui.folders.querySelectorAll('button[data-folder]').forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.folder === open));
        });
    }

    /** In a picker that selects, a folder says how many of its files are chosen ("2 gekozen in Teams"). */
    function countChosen() {
        ui.folders.querySelectorAll('button[data-folder]').forEach((button) => {
            const badge = slot('folder-chosen', button);

            if (! badge) {
                return;
            }

            const chosen = library || ! state.selecting || button.dataset.folder === ''
                ? 0
                : [...state.selected.values()].filter((file) => String(file.folder_id ?? '') === button.dataset.folder).length;

            badge.hidden = chosen === 0;
            badge.textContent = String(chosen);
            badge.setAttribute('aria-label', format(texts.folder_chosen, { count: chosen, folder: slot('folder-name', button)?.textContent ?? '' }));
        });
    }

    /**
     * The line of a picker of a list (design 13.4b–g): "Meerdere kiezen", pressed while it selects and off for a full
     * list; what is chosen beside it; the messages of the moment above the tiles and the hint below them.
     */
    function drawStand() {
        if (! stand.root || ! stand.hint) {
            return;
        }

        const many = state.many;
        const room = many?.room ?? null;
        const fullList = many !== null && room !== null && room <= 1;
        const count = state.selected.size;
        const lower = (text) => String(text ?? '').toLocaleLowerCase();

        stand.root.hidden = many === null;
        stand.many.setAttribute('aria-pressed', String(state.selecting));
        stand.many.setAttribute('aria-disabled', String(fullList));
        stand.full.hidden = ! fullList;
        stand.full.textContent = fullList ? format(texts.full, { list: many.list, count: many.count, max: many.max }) : '';

        let status = '';

        if (state.selecting) {
            const here = state.folder === null || [...state.selected.values()].some((file) => (file.folder_id ?? null) === state.folder.id);

            if (count === 0) {
                status = room === null ? texts.status_none_open : format(texts.status_none, { room });
            } else if (full()) {
                status = format(texts.status_max, { count });
            } else if (! here) {
                status = format(texts.status_elsewhere, { count });
            } else {
                status = room === null ? format(texts.status_open, { count }) : format(texts.status, { count, left: room - count });
            }
        }

        if (stand.status.textContent !== status) {
            stand.status.textContent = status;
        }

        const notices = [];

        if (state.selecting && state.started) {
            notices.push(texts.started);
        }

        if (state.selecting && full()) {
            notices.push(format(texts.limit, { list: many.list, max: many.max, items: lower(many.items) }));
        }

        if (state.over > 0) {
            notices.push(format(state.over === 1 ? texts.over_one : texts.over_many, { count: state.over, list: many?.list ?? '' }));
        }

        if (stand.notices.dataset.texts !== JSON.stringify(notices)) {
            stand.notices.dataset.texts = JSON.stringify(notices);
            stand.notices.replaceChildren(...notices.map((text) => {
                const notice = stand.notice.content.firstElementChild.cloneNode(true);

                notice.querySelector('.melding-tekst').textContent = text;

                return notice;
            }));
        }

        stand.hint.textContent = many === null
            ? texts.outside
            : (fullList
                ? format(texts.hint_full, { list: many.list, count: many.count, max: many.max, item: many.item })
                : (state.selecting ? texts.hint_many : texts.hint_one));
        countChosen();
    }

    /** The filters of the library are one group: every kind, a kind, or the images without an alt text. */
    function pressKinds() {
        if (! shelf) {
            return;
        }

        shelf.kinds.querySelectorAll('button[data-kind]').forEach((button) => {
            button.setAttribute('aria-pressed', String(! withoutAlt() && button.dataset.kind === (state.kind ?? '')));
        });
    }

    /** The numbers of the filters of the library, those of the whole library. */
    function count(counts) {
        if (! shelf || ! counts) {
            return;
        }

        shelf.kinds.querySelectorAll('button[data-kind]').forEach((button) => {
            const number = slot('kind-count', button);

            if (number) {
                number.textContent = String(counts[button.dataset.kind === '' ? 'all' : button.dataset.kind] ?? 0);
            }
        });
        shelf.withoutAltCount.textContent = String(counts.without_alt ?? 0);
        state.empty = (counts.all ?? 0) === 0;
        show();
    }

    function drawFolders(data) {
        slot('folder-count', all).textContent = String(data.all ?? '');
        ui.folders.querySelectorAll('li[data-folder-item]').forEach((item) => item.remove());

        for (const folder of data.folders) {
            const item = folderTemplate.content.firstElementChild.cloneNode(true);
            const button = item.querySelector('button');

            item.dataset.folderItem = '';
            button.dataset.folder = String(folder.id);
            slot('folder-name', item).textContent = folder.name;
            slot('folder-count', item).textContent = String(folder.count);
            // A chip of the library opens its folder, and once more all files again.
            button.addEventListener('click', () => open(library && state.folder?.id === folder.id ? null : { id: folder.id, name: folder.name }));

            if (library) {
                target(button, { id: folder.id, name: folder.name });
            }

            ui.folders.append(item);
        }

        count(data.counts);
        countChosen();

        if (library) {
            state.folderList = data.folders;
            tell('made-cms:media-folders', { folders: data.folders.map((folder) => ({ id: folder.id, name: folder.name })) });
        }
    }

    /** The folders with their numbers. A folder that left the library (someone removed it) takes the list back to all. */
    async function folders() {
        let data;

        try {
            const query = library
                ? { kind: kind(), without_alt: withoutAlt() ? 1 : null, q: search() }
                : { kind: settings.kind };
            const response = await fetch(url(settings.urls.folders, query), { credentials: 'same-origin', headers: HEADERS });

            data = response.ok ? await response.json() : null;
        } catch {
            data = null;
        }

        if (! Array.isArray(data?.folders)) {
            return;
        }

        drawFolders(data);

        const current = state.folder === null ? null : data.folders.find((folder) => folder.id === state.folder.id);

        if (state.folder !== null && ! current) {
            open(null);

            return;
        }

        if (current) {
            state.folder = { id: current.id, name: current.name };
        }

        pressFolders();
        trail();
    }

    /**
     * Opens a folder: the upload field follows it, the page hears it, and in the picker the session remembers it once
     * its files are in. Only while the list is in sight, so in the picker no save of the side panel runs alongside and
     * writes the session back.
     */
    function open(folder) {
        if ((folder?.id ?? null) === (state.folder?.id ?? null)) {
            return;
        }

        state.folder = folder;
        notice(null);

        if (state.field?.mode === 'rename') {
            field(null);
        }

        pressFolders();
        trail();
        follow(folder);
        tell('made-cms:media-folder', { folder });

        if (library) {
            refresh();

            return;
        }

        load().then(remember);
    }

    /** Sends every upload field of the list into a folder, also the one out of sight, so it is there when it shows. */
    function follow(folder) {
        for (const field of uploads) {
            field.dispatchEvent(new CustomEvent('made-cms:upload-folder', { detail: { folder } }));
        }
    }

    /** Listens to an event of the upload field in sight; a field out of sight has nothing to tell the list. */
    function hear(name, listener) {
        for (const field of uploads) {
            field.addEventListener(name, (event) => {
                if (field === upload) {
                    listener(event);
                }
            });
        }
    }

    /**
     * Shows the upload field of several photos for a picker that chooses several (many), else the field of one photo.
     * A list with one field keeps it.
     */
    function showUpload(many) {
        const wrappers = [...root.querySelectorAll('[data-picker-upload]')];
        const shown = wrappers.find((wrapper) => wrapper.dataset.pickerUpload === (many ? 'many' : 'one')) ?? wrappers[0];

        if (! shown) {
            return;
        }

        for (const wrapper of wrappers) {
            wrapper.hidden = wrapper !== shown;
        }

        upload = shown.querySelector('[data-upload]') ?? upload;
    }

    /** The list and its folders again, for the library whose folders count what it lists. */
    function refresh() {
        return Promise.all([load(), folders()]);
    }

    /**
     * Remembers the open folder of the picker for the session once its files are in, and only while it is open. A
     * folder the session did not take is sent again the next time the list loads. The library keeps its folder in
     * its address instead.
     */
    function remember(loaded = true) {
        const dialog = root.closest('dialog');
        const id = state.folder?.id ?? null;

        if (! loaded || dialog === null || id === state.remembered || ! dialog.open) {
            return;
        }

        fetch(settings.urls.chosen_folder, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: { ...HEADERS, 'Content-Type': 'application/json', 'X-CSRF-TOKEN': settings.csrf_token ?? '' },
            body: JSON.stringify({ folder: id }),
        }).then((response) => {
            if (response.ok) {
                state.remembered = id;
            }
        }).catch(() => {});
    }

    /** What the library shows: its start when it holds no file at all (unless files are on their way), else the list. */
    function show() {
        if (! shelf) {
            return;
        }

        const start = state.empty && ! state.uploading;

        root.toggleAttribute('data-empty-library', start);
        shelf.start.hidden = ! start;
    }

    /** The trail of the library: "Alle bestanden / Teams", and the folder the list of a file is in. */
    function trail() {
        if (! shelf) {
            return;
        }

        const inFolder = state.folder !== null;

        shelf.trailAll.hidden = ! inFolder;
        shelf.trailSeparator.hidden = ! inFolder;
        shelf.trailHere.textContent = inFolder ? state.folder.name : (texts.all_files ?? shelf.trailAll.textContent);
        root.toggleAttribute('data-in-folder', inFolder);
        actions();
    }

    /** "Hernoemen" and "Verwijderen" belong to the open folder, and make way for the field while it renames. */
    function actions() {
        shelf.folderActions.hidden = state.folder === null || state.field?.mode === 'rename';
    }

    /** The address of the library follows what it lists, so a reload or a link opens it the same. */
    function address() {
        if (! library) {
            return;
        }

        const target = new URL(window.location.pathname, window.location.href);
        const query = {
            folder: state.folder?.id,
            kind: withoutAlt() ? null : state.kind,
            without_alt: withoutAlt() ? 1 : null,
            q: search(),
            sort: pressed(ui.newest) ? null : 'name',
            file: state.chosen?.id,
        };

        for (const [key, value] of Object.entries(query)) {
            if (value !== null && value !== undefined && value !== '') {
                target.searchParams.set(key, String(value));
            }
        }

        try {
            window.history.replaceState(window.history.state, '', target);
        } catch {
            // A page that may not change its address keeps it.
        }
    }

    function view(name) {
        if (! shelf) {
            return;
        }

        root.dataset.view = name;
        shelf.tiles.setAttribute('aria-pressed', String(name === 'tiles'));
        shelf.list.setAttribute('aria-pressed', String(name === 'list'));
    }

    function paint(tile, file) {
        // While the list selects, a tile is pressed when it is selected; the chosen file waits until it stops.
        const selected = state.selecting && state.selected.has(file.id);
        const chosen = ! state.selecting && state.chosen?.id === file.id;

        tile.setAttribute('aria-pressed', String(chosen || selected));
        tile.classList.toggle('gekozen', chosen);
        tile.classList.toggle('geselecteerd', selected);

        // In the picker a selected file shows its place: the order of the items it becomes. Once the list of the item
        // takes no more, the other files say so (not grey: grey would read as "no image").
        const number = selected && ! library ? [...state.selected.keys()].indexOf(file.id) + 1 : 0;
        const limited = ! library && state.selecting && ! selected && full();

        if (number > 0) {
            tile.dataset.number = String(number);
        } else {
            delete tile.dataset.number;
        }

        if (! library) {
            const label = slot('tile-number', tile);

            if (label) {
                label.textContent = number > 0 ? String(number) : '';
            }

            tile.classList.toggle('vol', limited);

            if (limited) {
                tile.setAttribute('aria-disabled', 'true');
            } else {
                tile.removeAttribute('aria-disabled');
            }

            if (number > 0) {
                tile.setAttribute('aria-label', format(texts.tile_number, { name: file.name, n: number }));
            } else {
                tile.removeAttribute('aria-label');
            }
        }

        // In the picker the check of the choice takes the place of the dot; the library keeps it.
        slot('tile-no-alt', tile).hidden = ((chosen || selected) && ! library) || file.kind !== 'image' || (file.alt ?? '') !== '';
    }

    function tileOf(file) {
        const tile = tileTemplate.content.firstElementChild.cloneNode(true);
        const thumb = slot('tile-thumb', tile);
        const noAlt = file.kind === 'image' && (file.alt ?? '') === '';

        tile.dataset.id = String(file.id);
        tile.dataset.kind = file.kind ?? 'image';

        if (file.image) {
            const focal = file.image.focal ?? { x: 0.5, y: 0.5 };

            thumb.hidden = false;
            // The thumbnail, a few dozen kB, never the photo the site shows.
            thumb.src = file.thumb ?? file.url;
            thumb.style.objectPosition = (focal.x * 100) + '% ' + (focal.y * 100) + '%';
            // A thumbnail that does not load makes way for the icon.
            thumb.addEventListener('error', () => {
                thumb.hidden = true;
            });
        }

        slot('tile-extension', tile).textContent = file.extension ?? '';
        slot('tile-name', tile).textContent = file.name;
        slot('tile-meta', tile).textContent = file.meta ?? '';

        // A photo uploaded in this picker says it is new.
        if (! library && state.fresh.has(file.id)) {
            const fresh = slot('tile-new', tile);

            if (fresh) {
                fresh.hidden = false;
                fresh.textContent = texts.new ?? '';
            }

            slot('tile-meta', tile).textContent = format(texts.just, { meta: file.meta ?? '' });
        }

        if (library) {
            const kindName = settings.kinds?.[file.kind] ?? '';
            const sizeOf = String(file.meta ?? '').split(' · ').pop();
            const alt = slot('tile-alt', tile);

            slot('tile-short', tile).textContent = [file.extension, sizeOf].filter(Boolean).join(' · ');
            slot('tile-kind', tile).textContent = kindName;
            slot('tile-size', tile).textContent = file.meta ?? '';
            slot('tile-folder', tile).textContent = file.folder ?? '—';

            if ((file.alt ?? '') !== '') {
                const text = document.createElement('span');

                text.className = 'tegel-alt-tekst';
                text.textContent = file.alt;
                alt.append(text);
            } else if (noAlt) {
                const dot = document.createElement('span');
                const word = document.createElement('span');

                dot.className = 'tegel-alt-stip';
                dot.setAttribute('aria-hidden', 'true');
                word.className = 'tegel-alt-leeg';
                word.textContent = texts.alt_empty ?? '';
                alt.append(dot, word);
            } else {
                alt.textContent = '—';
            }

            tile.setAttribute('aria-label', format(noAlt ? texts.tile_no_alt : texts.tile, {
                name: file.file ?? file.name,
                kind: kindName.toLocaleLowerCase(),
            }));
        }

        // A click on the file that is chosen changes nothing: the side column of the library keeps what the editor typed.
        // While the list selects, or with Cmd/Ctrl or Shift, a click selects the file instead: in the library, and in a
        // picker opened from an item of a list.
        tile.addEventListener('click', (event) => {
            const current = state.files.find((candidate) => candidate.id === file.id) ?? file;

            if (selectable() && (state.selecting || event.metaKey || event.ctrlKey || event.shiftKey)) {
                pickFile(current, event);
            } else if (state.chosen?.id !== file.id) {
                choose(current);
            }
        });

        if (library) {
            tile.draggable = true;
            thumb.draggable = false;
            tile.addEventListener('dragstart', (event) => drag(event, file.id, tile));
            tile.addEventListener('dragend', dragged);
        }

        paint(tile, file);

        return tile;
    }

    function repaint() {
        for (const tile of ui.grid.children) {
            const file = state.files.find((candidate) => String(candidate.id) === tile.dataset.id);

            if (file) {
                paint(tile, file);
            }
        }
    }

    function choose(file) {
        if (state.chosen?.id === file.id) {
            commit(file);

            return;
        }

        // A host may hold the choice back (made-cms:media-choosing): at once by cancelling it, or until the promises it
        // hands to wait() resolved, as the side column of the library does while it stores a typed alt text. A promise
        // that gives false keeps the choice where it is, and a newer choice drops this one.
        const turn = ++state.turn;
        const waits = [];

        if (! root.dispatchEvent(new CustomEvent('made-cms:media-choosing', { bubbles: true, cancelable: true, detail: { file, wait: (promise) => waits.push(promise) } }))) {
            return;
        }

        if (waits.length === 0) {
            commit(file);

            return;
        }

        Promise.all(waits).then((results) => {
            if (turn === state.turn && results.every((result) => result !== false)) {
                commit(file);
            }
        }, () => {
            // A host that failed keeps the choice where it is.
        });
    }

    function commit(file) {
        state.chosen = file;
        repaint();
        address();
        tell('made-cms:media-chosen', { file });
    }

    /**
     * Whether the side column lets go of the file it shows, as before another choice (made-cms:media-choosing): a
     * typed alt text is stored first, and a refusal keeps it.
     */
    function letGo() {
        const waits = [];

        if (! root.dispatchEvent(new CustomEvent('made-cms:media-choosing', { bubbles: true, cancelable: true, detail: { file: null, wait: (promise) => waits.push(promise) } }))) {
            return Promise.resolve(false);
        }

        return Promise.all(waits).then((results) => results.every((result) => result !== false), () => false);
    }

    /** Whether the list selects files: the library, and a picker opened from an item of a list that takes another. */
    function selectable() {
        return library || (state.many !== null && (state.many.room === null || state.many.room > 1));
    }

    /** Whether the selection holds as many files as the list of a picker still takes. */
    function full() {
        const room = state.many?.room ?? null;

        return room !== null && state.selected.size >= room;
    }

    function tellSelection() {
        drawStand();
        tell('made-cms:media-selection', { selecting: state.selecting, files: [...state.selected.values()], full: full() });
    }

    /**
     * Starts or stops selecting. Selecting starts with the files given, else the chosen file: in the library when the
     * list shows it, in the picker wherever it is (the photo the item has when the picker opens), as the first of the
     * items. Stopping with one file selected chooses it; with more nothing is chosen, without any the choice stays.
     */
    async function selecting(on, start = null) {
        if (on === state.selecting) {
            return true;
        }

        if (on && ! selectable()) {
            return false;
        }

        if (on) {
            if (! await letGo()) {
                return false;
            }

            const chosen = library
                ? state.chosen && state.files.find((candidate) => candidate.id === state.chosen.id)
                : state.chosen;

            state.selecting = true;
            state.selected = new Map((start ?? (chosen ? [chosen] : [])).map((file) => [file.id, file]));
            state.anchor = chosen?.id ?? null;
        }

        const picked = [...state.selected.values()];

        if (! on) {
            state.selecting = false;
            state.selected = new Map();
            state.anchor = null;
            state.started = false;
        }

        root.toggleAttribute('data-selecting', state.selecting);
        shelf?.select.setAttribute('aria-pressed', String(state.selecting));
        repaint();
        tellSelection();

        if (! on && picked.length === 1) {
            commit(picked[0]);
        } else if (! on && picked.length > 1) {
            commit(null);
        }

        return true;
    }

    /**
     * A click while selecting: the file in or out, with Shift the range from the file clicked before, in the order of
     * the list (a file in it that is selected keeps its place). In the picker nothing joins once the list of the item
     * is full: the rest of a range is left out. An anchor the list does not show makes Shift-click a plain click.
     */
    async function pickFile(file, event) {
        if (! state.selecting && ! await selecting(true)) {
            return;
        }

        const ids = state.files.map((candidate) => candidate.id);
        const from = state.anchor === null ? -1 : ids.indexOf(state.anchor);
        const to = ids.indexOf(file.id);
        let refused = false;
        const join = (candidate) => {
            if (state.selected.has(candidate.id) || ! full()) {
                state.selected.set(candidate.id, candidate);
            } else {
                refused = true;
            }
        };

        if (event.shiftKey && from >= 0 && to >= 0) {
            const range = state.files.slice(Math.min(from, to), Math.max(from, to) + 1);

            // In the picker the numbers follow the click: a range upwards counts upwards.
            (to < from && ! library ? range.reverse() : range).forEach(join);
        } else if (state.selected.has(file.id)) {
            state.selected.delete(file.id);
        } else {
            join(file);
        }

        state.anchor = file.id;
        repaint();
        tellSelection();

        // A photo the list no longer takes says why again, so a click or Enter on it is never silent.
        if (refused && stand.notices) {
            stand.notices.dataset.texts = '';
            stand.notices.replaceChildren();
            window.setTimeout(drawStand, 50);
        }
    }

    /**
     * One message below the folders: what a folder or a move did. It stays until the editor closes it, the next one
     * comes, or another folder or filter; null takes it away. A link opens the folder files went to.
     */
    function notice(text, tone = 'goed', link = undefined) {
        if (! shelf) {
            return null;
        }

        if (text === null) {
            shelf.notice.replaceChildren();

            return null;
        }

        const box = noticeTemplate.content.firstElementChild.cloneNode(true);
        const go = slot('notice-link', box);

        box.classList.add(tone);
        box.querySelector('.bol')?.classList.add(tone);
        slot('notice-text', box).textContent = text;
        go.hidden = link === undefined;

        if (link !== undefined) {
            go.textContent = format(texts.open_folder, { folder: link?.name ?? texts.all_files });
            go.addEventListener('click', () => open(link));
        }

        slot('notice-close', box).addEventListener('click', () => notice(null));
        shelf.notice.replaceChildren(box);

        return box;
    }

    /** The field below the chips, for a new folder or a new name of the open one; null closes it. */
    function field(mode) {
        state.field = mode === null ? null : { mode, busy: false };
        newFolder?.setAttribute('aria-expanded', String(mode === 'new'));
        actions();

        if (mode === null) {
            shelf.folderField.hidden = true;
            shelf.folderInput.disabled = false;

            return;
        }

        const renaming = mode === 'rename';

        notice(null);
        shelf.folderField.hidden = false;
        shelf.folderField.setAttribute('aria-label', renaming ? texts.folder_rename : texts.folder_new);
        shelf.folderLabel.textContent = renaming ? format(texts.folder_rename_label, { folder: state.folder?.name ?? '' }) : texts.folder_new_label;
        shelf.folderInput.value = renaming ? (state.folder?.name ?? '') : '';
        busy(false);
        fieldError(null);
        shelf.folderInput.focus();

        if (renaming) {
            shelf.folderInput.select();
        }
    }

    function busy(on) {
        if (state.field) {
            state.field.busy = on;
        }

        shelf.folderInput.disabled = on;
        shelf.folderConfirm.disabled = on;
        shelf.folderConfirm.textContent = on ? texts.saving : (state.field?.mode === 'rename' ? texts.folder_save : texts.folder_create);
    }

    function fieldError(error) {
        shelf.folderHint.textContent = error ?? format(state.field?.mode === 'rename' ? texts.folder_rename_hint : texts.folder_new_hint, { max: settings.folder_max ?? 60 });
        shelf.folderHint.classList.toggle('fout', error !== null);
        shelf.folderInput.classList.toggle('fout', error !== null);
        shelf.folderInput.setAttribute('aria-invalid', String(error !== null));
        error === null ? shelf.folderHint.removeAttribute('role') : shelf.folderHint.setAttribute('role', 'alert');
    }

    const sending = (method, body = null) => ({
        method,
        credentials: 'same-origin',
        headers: { ...HEADERS, 'Content-Type': 'application/json', 'X-CSRF-TOKEN': settings.csrf_token ?? '' },
        body: body === null ? null : JSON.stringify(body),
    });
    const folderUrl = (id) => settings.urls.folder.replace(':folder', String(id));
    const failure = (response) => (response?.status === 401 || response?.status === 419 ? texts.expired : texts.action_failed);

    /** Creates the folder or stores the new name; a refusal stays at the field. */
    async function confirmField() {
        const current = state.field;

        if (current === null || current.busy) {
            return;
        }

        const renaming = current.mode === 'rename';
        const folder = state.folder;
        const name = shelf.folderInput.value.trim();

        if (renaming && (folder === null || name === folder.name)) {
            field(null);
            shelf.rename.focus();

            return;
        }

        let response = null;
        let data = null;

        busy(true);

        try {
            response = await fetch(renaming ? folderUrl(folder.id) : settings.urls.folders_store, sending(renaming ? 'PUT' : 'POST', { name }));
            data = await response.json().catch(() => null);
        } catch {
            response = null;
        }

        if (state.field !== current) {
            return;
        }

        busy(false);

        if (response?.status === 404) {
            field(null);
            notice(texts.folder_gone, 'alarm');
            folders();

            return;
        }

        if (! response?.ok || ! data?.folder) {
            fieldError(response?.status === 422 ? (first(data?.errors?.name) ?? texts.action_failed) : failure(response));
            shelf.folderInput.focus();

            return;
        }

        field(null);

        if (! renaming) {
            notice(format(texts.folder_created, { folder: data.folder.name }));
            await folders();
            ui.folders.querySelector('button[data-folder="' + data.folder.id + '"]')?.focus();

            return;
        }

        // The open folder keeps its id: the trail, the upload field and the page hear its new name.
        state.folder = { id: data.folder.id, name: data.folder.name };
        trail();
        follow(state.folder);
        tell('made-cms:media-folder', { folder: state.folder });
        notice(format(texts.folder_renamed, { folder: data.folder.name }));
        shelf.rename.focus();
        await refresh();
        changedChoice();
    }

    /** The chosen file as the list holds it now, for the side column (its folder line after a new name). */
    function changedChoice() {
        const fresh = state.chosen && state.files.find((candidate) => candidate.id === state.chosen.id);

        if (fresh) {
            state.chosen = fresh;
            tell('made-cms:media-changed', { file: fresh });
        }
    }

    /** Deletes the open folder when it is empty; otherwise the message says how many files it still holds. */
    async function removeFolder() {
        const folder = state.folder;

        if (folder === null) {
            return;
        }

        let response = null;

        notice(null);
        shelf.remove.disabled = true;

        try {
            response = await fetch(folderUrl(folder.id), sending('DELETE'));
        } catch {
            response = null;
        } finally {
            shelf.remove.disabled = false;
        }

        if (response?.status === 204 || response?.status === 404) {
            open(null);
            notice(response.status === 204 ? format(texts.folder_deleted, { folder: folder.name }) : texts.folder_gone, response.status === 204 ? 'goed' : 'alarm');
            slot('notice-close', shelf.notice)?.focus();

            return;
        }

        const data = await response?.json().catch(() => null);

        notice(response?.status === 422 ? (first(data?.errors?.folder) ?? texts.action_failed) : failure(response), response?.status === 422 ? 'let' : 'alarm');
    }

    /**
     * Moves files into a folder ({id, name}) or into "Alle bestanden" (null), all or none of them. The message says
     * where they are now, with a link when they left the folder the list shows; the list and its numbers load again,
     * and a chosen file that left the open folder leaves the side column. A typed alt text of the chosen file is stored
     * first, as before another choice: when it is not, nothing moves and the side column says why (error null).
     *
     * @return {Promise<{ok: boolean, error: string|null}>}
     */
    async function move(ids, folder) {
        let response = null;
        let data = null;

        if (state.chosen !== null && ids.includes(state.chosen.id) && ! await letGo()) {
            return { ok: false, error: null };
        }

        try {
            response = await fetch(settings.urls.move_many, sending('PUT', { files: ids, folder: folder?.id ?? null }));
            data = await response.json().catch(() => null);
        } catch {
            response = null;
        }

        if (! response?.ok || ! Array.isArray(data?.files)) {
            if (response?.status === 422) {
                folders();
            }

            return { ok: false, error: response?.status === 422 ? (first(data?.errors?.folder) ?? first(data?.errors?.files) ?? texts.action_failed) : failure(response) };
        }

        const moved = data.files;
        const name = folder?.name ?? texts.all_files;
        const stays = state.folder === null || state.folder.id === (folder?.id ?? null);
        const chosen = moved.find((file) => file.id === state.chosen?.id) ?? null;

        if (moved.length > 1) {
            notice(format(texts.moved_many, { count: moved.length, folder: name }), 'goed', stays ? undefined : folder);
        } else if (moved.length === 1) {
            const here = stays && ! state.selecting && chosen !== null;

            notice(format(here ? texts.moved_here : texts.moved_one, { name: moved[0].file ?? moved[0].name, folder: name }), 'goed', stays ? undefined : folder);
        }

        // What moved leaves the selection, also when the list still shows it; the mode stays.
        if (state.selecting && ids.some((id) => state.selected.has(id))) {
            ids.forEach((id) => state.selected.delete(id));
            tellSelection();
            repaint();
        }

        await refresh();

        if (chosen !== null && ! stays) {
            commit(null);
        } else if (chosen !== null) {
            state.chosen = state.files.find((file) => file.id === chosen.id) ?? chosen;
            tell('made-cms:media-changed', { file: state.chosen });
        }

        return { ok: true, error: null };
    }

    /**
     * Deletes files that no page uses, from the side column (made-cms:media-delete): the server keeps what a page uses
     * and says what it did. The message tells what left; what stayed is told only of a selection (the side column says
     * it of the one file it shows). What left leaves the selection and the side column; the list loads again.
     *
     * @return {Promise<{ok: boolean, deleted: number[], kept: number[], error: string|null}>}
     */
    async function remove(ids, selection = false) {
        let response = null;
        let data = null;

        try {
            response = await fetch(settings.urls.delete, sending('POST', { files: ids }));
            data = await response.json().catch(() => null);
        } catch {
            response = null;
        }

        if (! response?.ok || ! Array.isArray(data?.deleted)) {
            return { ok: false, deleted: [], kept: [], error: response?.status === 422 ? (first(data?.errors?.files) ?? texts.action_failed) : failure(response) };
        }

        const gone = [...data.deleted, ...(data.missing ?? [])];
        const told = [data.notice, selection ? data.refusal : null].filter(Boolean).join(' ');

        if (told !== '') {
            notice(told, data.deleted.length > 0 ? 'goed' : 'let');
        }

        if (state.selecting && gone.some((id) => state.selected.has(id))) {
            gone.forEach((id) => state.selected.delete(id));
            tellSelection();
        }

        await refresh();

        if (state.chosen !== null && gone.includes(state.chosen.id)) {
            commit(null);
        }

        // The button that deleted is gone with the file: the message takes the focus, as after deleting a folder.
        if (data.deleted.length > 0) {
            slot('notice-close', shelf.notice)?.focus();
        }

        return { ok: true, deleted: data.deleted, kept: data.kept ?? [], error: null };
    }

    /** A tile starts a drag: the selection when it is selected, else the tile alone. */
    function drag(event, id, tile) {
        const ids = state.selecting && state.selected.has(id) ? [...state.selected.keys()] : [id];

        state.dragging = ids;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData(DRAG_TYPE, JSON.stringify(ids));

        if (ids.length > 1) {
            event.dataTransfer.setDragImage(stack(ids.length, tile), 28, 28);
        }

        root.toggleAttribute('data-dragging', true);

        for (const button of ui.folders.querySelectorAll('li[data-folder-item] button[data-folder]')) {
            const open = button.dataset.folder === String(state.folder?.id ?? '');

            button.classList.toggle('doel', ! open);
            button.classList.toggle('geen-doel', open);
        }

        shelf.trailAll.classList.toggle('doel', state.folder !== null);

        for (const element of ui.grid.children) {
            element.classList.toggle('sleept', ids.includes(Number(element.dataset.id)));
        }
    }

    /** What the browser shows under the pointer for a selection: a stack of two with the number on it. */
    function stack(number, tile) {
        document.querySelector('.mediabrowser-sleepbeeld')?.remove();

        const image = document.createElement('div');
        const front = tile.querySelector('.tegel-beeld').cloneNode(true);
        const count = document.createElement('span');

        image.className = 'mediabrowser-sleepbeeld';
        image.setAttribute('aria-hidden', 'true');
        front.classList.add('voor');
        count.className = 'mediabrowser-sleeptel';
        count.textContent = String(number);
        image.append(document.createElement('span'), front, count);
        image.firstElementChild.className = 'achter';
        document.body.append(image);

        return image;
    }

    function dragged() {
        state.dragging = null;
        root.removeAttribute('data-dragging');
        document.querySelector('.mediabrowser-sleepbeeld')?.remove();
        root.querySelectorAll('.doel, .doel-actief, .geen-doel, .sleept').forEach((element) => {
            element.classList.remove('doel', 'doel-actief', 'geen-doel', 'sleept');
        });
    }

    /** A folder chip, or "Alle bestanden" in the trail, takes the tiles dragged onto it, but not the open folder. */
    function target(element, folder) {
        const takes = (event) => state.dragging !== null
            && [...(event.dataTransfer?.types ?? [])].includes(DRAG_TYPE)
            && (folder?.id ?? null) !== (state.folder?.id ?? null);

        element.addEventListener('dragenter', (event) => {
            if (takes(event)) {
                event.preventDefault();
                element.classList.add('doel-actief');
            }
        });
        element.addEventListener('dragover', (event) => {
            if (takes(event)) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                element.classList.add('doel-actief');
            }
        });
        element.addEventListener('dragleave', (event) => {
            if (! element.contains(event.relatedTarget)) {
                element.classList.remove('doel-actief');
            }
        });
        element.addEventListener('drop', async (event) => {
            if (! takes(event)) {
                return;
            }

            event.preventDefault();

            const ids = state.dragging;

            dragged();

            const result = await move(ids, folder);

            if (! result.ok && result.error !== null) {
                notice(result.error, 'alarm');
            }
        });
    }

    function fail(message) {
        ui.failed.textContent = message;
        ui.failed.hidden = false;
        ui.status.textContent = message;
    }

    /** What an empty list of the library says, and the way on it offers. */
    function empty() {
        if (! shelf) {
            ui.empty.hidden = state.files.length > 0;

            return;
        }

        const none = state.files.length === 0;
        const folder = state.folder?.name ?? null;
        const words = (key, values = {}) => format(texts[folder !== null && texts[key + '_folder'] ? key + '_folder' : key], { folder, ...values });
        let upload = false;
        let link = null;
        let title = '';
        let text = '';

        if (search() !== '') {
            title = format(texts.nothing_found, { search: search() });
            text = words('searched');
            link = folder !== null
                ? [texts.search_all, () => open(null)]
                : [texts.clear_search, () => {
                    ui.search.value = '';
                    refresh();
                    ui.search.focus();
                }];
        } else if (withoutAlt()) {
            title = words('all_described');
            text = texts.nothing_to_do;
            link = [words('show_all'), () => kindTo(null)];
        } else if (state.kind !== null) {
            // The name of the filter, "Documenten", as "Geen documenten".
            const button = shelf.kinds.querySelector('button[data-kind="' + state.kind + '"]');
            const kinds = (button?.firstChild?.textContent ?? '').trim().toLocaleLowerCase();

            title = words('none_of_kind', { kinds });
            text = words('others');
            link = [words('show_all'), () => kindTo(null)];
        } else if (folder !== null) {
            title = texts.empty_folder;
            text = format(texts.empty_folder_text, { folder });
            upload = true;
        } else {
            title = texts.uploading;
        }

        ui.empty.hidden = ! none;
        shelf.emptyTitle.textContent = title;
        shelf.emptyText.textContent = text;
        shelf.emptyText.hidden = text === '';
        shelf.emptyUpload.hidden = ! upload;
        shelf.emptyLink.hidden = link === null;
        shelf.emptyLink.textContent = link?.[0] ?? '';
        shelf.emptyLink.onclick = link?.[1] ?? null;
        shelf.note.hidden = none;
        shelf.note.textContent = folder !== null ? texts.note_folder : texts.note_all;
    }

    /** What the list asks its endpoint, without the page: another answer is another list. */
    function listing() {
        return {
            folder: state.folder?.id,
            q: search(),
            kind: kind(),
            without_alt: withoutAlt() ? 1 : null,
            sort: pressed(ui.newest) ? 'newest' : 'name',
        };
    }

    /** Draws a page of the list as its endpoint answers it, for the question of listing() when it was asked. */
    function draw(data, more = false, question = JSON.stringify(listing())) {
        // The selection of the picker is its result: it stays over another folder, filter or search, and a file it holds
        // takes the form the list shows now.
        if (! library && state.selecting) {
            const shown = data.files.filter((file) => state.selected.has(file.id));

            shown.forEach((file) => state.selected.set(file.id, file));

            if (shown.length > 0) {
                tellSelection();
            }
        }

        // The selection of the library belongs to the list the editor sees: another folder, filter, search or order
        // starts it again, and the same list loaded again (a file that came in, a folder renamed) keeps the files it
        // still shows.
        if (library && ! more && state.selecting) {
            const shown = new Map(data.files.map((file) => [file.id, file]));
            const before = state.selected.size;

            state.selected = question === state.listed
                ? new Map([...state.selected.keys()].filter((id) => shown.has(id)).map((id) => [id, shown.get(id)]))
                : new Map();
            state.anchor = question === state.listed && shown.has(state.anchor) ? state.anchor : null;

            if (question !== state.listed || state.selected.size !== before) {
                tellSelection();
            }
        }

        if (! more) {
            state.listed = question;
        }

        const tiles = data.files.map(tileOf);

        state.page = data.page;
        state.total = data.total ?? 0;
        state.files = more ? [...state.files, ...data.files] : data.files;
        more ? ui.grid.append(...tiles) : ui.grid.replaceChildren(...tiles);
        ui.more.hidden = ! data.more;
        ui.status.textContent = data.summary ?? '';
        empty();
        address();

        return tiles;
    }

    /** The first page of the list, or with more the next one below it. A newer request drops an older answer. */
    async function load(more = false) {
        state.loading?.abort();

        const controller = new AbortController();
        const page = more ? state.page + 1 : 1;
        const question = listing();

        state.loading = controller;
        root.dataset.loading = '';
        ui.failed.hidden = true;

        try {
            const response = await fetch(url(settings.urls.files, { ...question, page }), { credentials: 'same-origin', headers: HEADERS, signal: controller.signal });
            const data = await response.json().catch(() => null);

            if (! response.ok || ! Array.isArray(data?.files)) {
                // The folder left the library: the folders take the list back to all files.
                if (response.status === 422 && data?.errors?.folder) {
                    folders();
                }

                fail(response.status === 401 || response.status === 419 ? texts.expired : texts.failed);

                return false;
            }

            const tiles = draw(data, more, JSON.stringify(question));

            // "Meer tonen" leads on to the first file it brought.
            if (more) {
                (tiles[0] ?? ui.search).focus();
            }

            return true;
        } catch (error) {
            if (error?.name !== 'AbortError') {
                fail(texts.failed);
            }

            return false;
        } finally {
            if (state.loading === controller) {
                state.loading = null;
                delete root.dataset.loading;
            }
        }
    }

    /** A filter of the library: every kind (null), a kind, or with withoutAlt the images without an alt text. */
    function kindTo(next, without = false) {
        if (next === state.kind && without === withoutAlt()) {
            return;
        }

        state.kind = without ? null : next;
        ui.withoutAlt.setAttribute('aria-pressed', String(without));
        notice(null);
        pressKinds();
        refresh();
    }

    /** Back to the start of the list: no search, newest first, every file the list shows. */
    function reset() {
        window.clearTimeout(typing);
        ui.search.value = '';
        ui.newest.setAttribute('aria-pressed', 'true');
        ui.withoutAlt.setAttribute('aria-pressed', 'false');
        state.kind = null;
        pressKinds();
    }

    all.addEventListener('click', () => open(null));
    ui.search.addEventListener('input', () => {
        window.clearTimeout(typing);
        typing = window.setTimeout(() => (library ? refresh() : load()), SEARCH_AFTER);
    });
    ui.search.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            window.clearTimeout(typing);
            library ? refresh() : load();
        }
    });

    ui.newest.addEventListener('click', () => {
        ui.newest.setAttribute('aria-pressed', String(! pressed(ui.newest)));
        load();
    });

    if (shelf) {
        // One filter of the group is pressed; pressing it again keeps it.
        shelf.kinds.querySelectorAll('button[data-kind]').forEach((button) => {
            button.addEventListener('click', () => kindTo(button.dataset.kind === '' ? null : button.dataset.kind));
        });
        ui.withoutAlt.addEventListener('click', () => kindTo(null, true));
        shelf.trailAll.addEventListener('click', () => open(null));
        target(shelf.trailAll, null);

        newFolder?.addEventListener('click', () => (state.field?.mode === 'new' ? field(null) : field('new')));
        shelf.rename.addEventListener('click', () => field('rename'));
        shelf.remove.addEventListener('click', removeFolder);
        shelf.folderConfirm.addEventListener('click', confirmField);
        shelf.folderCancel.addEventListener('click', () => {
            const renaming = state.field?.mode === 'rename';

            field(null);
            (renaming ? shelf.rename : newFolder)?.focus();
        });
        shelf.folderInput.addEventListener('input', () => {
            if (shelf.folderHint.classList.contains('fout')) {
                fieldError(null);
            }
        });
        shelf.folderInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                confirmField();
            } else if (event.key === 'Escape') {
                // Escape here closes the field only, never the selection.
                event.preventDefault();
                event.stopPropagation();
                shelf.folderCancel.click();
            }
        });

        shelf.select.addEventListener('click', () => selecting(! state.selecting));
        root.addEventListener('made-cms:media-select', (event) => selecting(event.detail?.on === true));
        root.addEventListener('made-cms:media-move', (event) => {
            const detail = event.detail ?? {};

            detail.taken = true;
            move(detail.files ?? [], detail.folder ?? null).then((result) => detail.done?.(result));
        });
        root.addEventListener('made-cms:media-delete', (event) => {
            const detail = event.detail ?? {};

            detail.taken = true;
            remove(detail.files ?? [], detail.selection === true).then((result) => detail.done?.(result));
        });
        // What the side column did to a file (a new version, a version put back) is told in the same place.
        root.addEventListener('made-cms:media-notice', (event) => {
            if (event.detail?.text) {
                notice(event.detail.text, event.detail.tone ?? 'goed');
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || ! state.selecting || event.defaultPrevented) {
                return;
            }

            if (event.target instanceof Element && event.target.closest('dialog, input, textarea, select')) {
                return;
            }

            selecting(false);
        });

        for (const [button, name] of [[shelf.tiles, 'tiles'], [shelf.list, 'list']]) {
            button.addEventListener('click', () => {
                view(name);

                try {
                    window.localStorage.setItem('made-cms-media-view', name);
                } catch {
                    // Without storage the view holds until the page is left.
                }
            });
        }
    } else {
        ui.withoutAlt.addEventListener('click', () => {
            ui.withoutAlt.setAttribute('aria-pressed', String(! withoutAlt()));
            load();
        });
    }

    ui.more.addEventListener('click', () => load(true));

    hear('made-cms:upload', (event) => {
        state.uploading = event.detail?.busy === true || (event.detail?.total ?? 0) > 0;
        show();

        // More than one photo on its way for a picker of a list: they all join, as the next items. A photo that came in
        // earlier and waits for its alt text is no part of this batch.
        const coming = (event.detail?.total ?? 0) - (event.detail?.done ?? 0);

        if (! library && selectable() && ! state.selecting && coming > 1) {
            state.started = true;
            selecting(true);
        }
    });

    // A file uploaded in the picker is the choice, at the top of the list it is in. The library keeps its filter, search
    // and order, and shows a new file where they place it, without choosing it: a batch would make the side column
    // jump, and an editor working through a filter keeps it. The upload field shows every file while it comes in.
    hear('made-cms:uploaded', (event) => {
        const file = event.detail?.file;

        if (! file) {
            return;
        }

        if (library) {
            window.clearTimeout(refreshing);
            refreshing = window.setTimeout(refresh, 150);

            return;
        }

        // While the picker selects, a photo joins the selection as long as the list of the item takes it (else it is
        // in Media only), and the list loads once the photos of a batch came in.
        state.fresh.add(file.id);

        if (state.selecting) {
            if (! state.selected.has(file.id) && ! full()) {
                state.selected.set(file.id, file);
            } else if (! state.selected.has(file.id)) {
                state.over++;
            }

            tellSelection();

            window.clearTimeout(refreshing);
            refreshing = window.setTimeout(() => {
                reset();
                load();
                folders();
            }, 150);

            return;
        }

        reset();
        choose(file);
        load();
        folders();
    });

    // The alt text the editor gave the new file, and its name and crop from the crop step, as they are stored.
    hear('made-cms:upload-closed', (event) => {
        for (const file of event.detail?.files ?? []) {
            replace(file);
        }

        repaint();

        if (library) {
            folders();
        }
    });

    /**
     * A file as it is stored now, in place of the one the list holds. An answer that is not a page of the list (an alt
     * text, a crop, a new version) does not say whether a page uses the file or give its lasting link: those stay.
     */
    function replace(changed) {
        const before = state.files.find((candidate) => candidate.id === changed.id) ?? state.selected.get(changed.id);
        const file = { used: before?.used, link: before?.link, ...changed };

        state.files = state.files.map((candidate) => (candidate.id === file.id ? file : candidate));

        if (state.selected.has(file.id)) {
            state.selected.set(file.id, file);
            tellSelection();
        }

        ui.grid.querySelector('.tegel[data-id="' + file.id + '"]')?.replaceWith(tileOf(file));

        if (state.chosen?.id === file.id) {
            if (library) {
                state.chosen = file;
            } else {
                choose(file);
            }
        }
    }

    // A file that changed elsewhere on the page (its alt text in the side column of the library, a crop, a name or
    // a folder in the crop step): its tile shows it at once; the numbers follow, and a file that went into another
    // folder leaves the one the list is in.
    if (library) {
        document.addEventListener('made-cms:media-changed', (event) => {
            const file = event.detail?.file;

            // A file the list moved itself is loaded again already.
            if (event.target === root) {
                return;
            }

            if (! file || ! state.files.some((candidate) => candidate.id === file.id)) {
                folders();

                return;
            }

            const before = state.files.find((candidate) => candidate.id === file.id);

            replace(file);
            repaint();

            if (state.folder !== null && (before?.folder ?? null) !== (file.folder ?? null)) {
                refresh();
            } else {
                folders();
            }
        });
    }

    // In a picker opened from an item of a list, made-cms:media-select {on} or its button starts and stops selecting,
    // and made-cms:media-deselect {id} takes a file out (the cross of its chip at the foot).
    if (! library) {
        root.addEventListener('made-cms:media-select', (event) => selecting(event.detail?.on === true));
        stand.many?.addEventListener('click', () => {
            if (stand.many.getAttribute('aria-disabled') !== 'true') {
                selecting(! state.selecting);
            }
        });
        root.addEventListener('made-cms:media-deselect', (event) => {
            if (state.selecting && state.selected.delete(event.detail?.id)) {
                repaint();
                tellSelection();
            }
        });
    }

    root.addEventListener('made-cms:media-load', (event) => {
        // A photo the server was still storing when the list was left asks no alt text any more: it is "later".
        uploads.forEach(finished);
        state.many = event.detail?.many ?? null;
        // The field of several photos only for a list that takes more than the photo of its item.
        showUpload(selectable());
        root.toggleAttribute('data-many', ! library && selectable());
        state.selecting = false;
        state.selected = new Map();
        state.anchor = null;
        state.started = false;
        state.over = 0;
        state.fresh = new Set();
        root.removeAttribute('data-selecting');
        shelf?.select.setAttribute('aria-pressed', 'false');
        tellSelection();
        state.turn++;
        state.chosen = event.detail?.file ?? null;
        state.files = [];
        reset();
        ui.grid.replaceChildren();
        ui.more.hidden = true;
        ui.empty.hidden = true;
        folders();
        load().then(remember);
    });

    if (library) {
        let stored = null;

        try {
            stored = window.localStorage.getItem('made-cms-media-view');
        } catch {
            stored = null;
        }

        view(stored === 'list' ? 'list' : 'tiles');
        pressKinds();
        trail();

        // The first page and the folders came along with the page: no request before the editor does something.
        if (settings.initial) {
            drawFolders(settings.initial.folders);
            pressFolders();
            draw(settings.initial.files);
        } else {
            refresh();
        }

        const chosen = settings.chosen === null || settings.chosen === undefined
            ? null
            : state.files.find((file) => file.id === settings.chosen);

        if (chosen) {
            choose(chosen);
        }
    } else if (root.closest('dialog') === null) {
        folders();
        load();
    }
}

function pick(dialog) {
    const settings = configOf(dialog);
    const browser = dialog.querySelector('[data-media]');

    if (! settings || ! browser) {
        return;
    }

    const texts = settings.texts ?? {};
    const slot = (name) => dialog.querySelector('[data-slot="media-picker-' + name + '"]');
    const ui = {
        for: slot('for'),
        close: slot('close'),
        thumb: slot('thumb'),
        name: slot('name'),
        alt: slot('alt'),
        cancel: slot('cancel'),
        use: slot('use'),
    };
    // The foot of a picker of a list while it selects: the number chosen, where they go, and a chip per file in order.
    const foot = {
        one: slot('one'),
        selection: slot('selection'),
        chosen: slot('chosen'),
        where: slot('where'),
        chips: slot('chips'),
        chip: dialog.querySelector('template[data-picker-chip]'),
    };
    // The field of one photo and the field of several photos; the list shows one of them each time the picker opens.
    const uploads = [...browser.querySelectorAll('[data-upload]')];
    const search = browser.querySelector('[data-slot="media-search"]');

    if (Object.values(ui).some((element) => element === null)) {
        return;
    }

    let request = null;
    let chosen = null;
    // What the list selects in a picker opened from an item of a list: the files in the order of the items they become.
    let selection = { selecting: false, files: [], full: false };
    // The upload fields whose bytes still travel, so they can still be stopped.
    const sending = new Set();
    let downOutside = false;
    // Waiting for the upload field to finish, so a second click does nothing.
    let finishing = false;

    /** The files "Deze gebruiken" hands the side panel: the selection while the list selects, else the choice. */
    function picked() {
        if (selection.selecting) {
            return selection.files;
        }

        return chosen ? [chosen] : [];
    }

    function draw() {
        const files = picked();
        const first = files[0] ?? null;
        const alt = first?.alt ?? '';

        ui.name.textContent = files.length > 0 ? files.map((file) => file.name).join(', ') : texts.nothing;
        ui.alt.textContent = files.length > 1 ? '' : (first ? (alt === '' ? texts.no_alt : format(texts.alt, { alt })) : texts.nothing_hint);
        ui.alt.dataset.alt = files.length === 1 ? (alt === '' ? 'missing' : 'set') : '';
        ui.thumb.hidden = ! first?.image;

        const small = first?.thumb ?? first?.url;

        if (first?.image && ui.thumb.getAttribute('src') !== small) {
            ui.thumb.src = small;
        }

        ui.use.textContent = files.length === 0
            ? texts.choose_first
            : (selection.selecting && files.length > 1 ? format(texts.use_many, { count: files.length }) : texts.use);
        ui.use.setAttribute('aria-disabled', String(files.length === 0));
        drawSelection();
    }

    /** The foot while the list selects (design 13.4c): what is chosen and where it goes, a chip per file in order. */
    function drawSelection() {
        if (! foot.selection || ! foot.chips || ! foot.chip) {
            return;
        }

        const files = selection.selecting ? selection.files : [];
        const item = request?.many?.item ?? '';
        const focused = foot.chips.contains(document.activeElement)
            ? [...foot.chips.children].findIndex((chip) => chip.contains(document.activeElement))
            : -1;

        foot.one.hidden = selection.selecting;
        foot.selection.hidden = ! selection.selecting;
        foot.chosen.textContent = files.length === 0 ? texts.nothing : format(texts.chosen, { count: files.length });
        foot.where.textContent = files.length === 0
            ? texts.foot_none
            : format(files.length === 1 ? texts.foot_one : texts.foot_many, { item });
        foot.chips.replaceChildren(...files.map((file, index) => {
            const chip = foot.chip.content.firstElementChild.cloneNode(true);
            const thumb = chip.querySelector('[data-slot="media-picker-chip-thumb"]');
            const remove = chip.querySelector('[data-slot="media-picker-chip-remove"]');

            chip.title = file.name;
            chip.querySelector('[data-slot="media-picker-chip-number"]').textContent = String(index + 1);

            if (file.image) {
                thumb.src = file.thumb ?? file.url;
                thumb.hidden = false;
            }

            remove.setAttribute('aria-label', format(texts.chip_remove, { name: file.name }));
            remove.addEventListener('click', () => browser.dispatchEvent(new CustomEvent('made-cms:media-deselect', { detail: { id: file.id } })));

            return chip;
        }));

        // The cross of a chip that went gives the focus to the chip that took its place, else to the button.
        if (focused >= 0) {
            const next = foot.chips.children[Math.min(focused, foot.chips.children.length - 1)];

            (next?.querySelector('[data-slot="media-picker-chip-remove"]') ?? ui.use).focus();
        }
    }

    /** Tab goes round inside the dialog, never out to the browser. */
    function wrap(event) {
        const focusable = [...dialog.querySelectorAll('button, input, select, textarea, a[href], [tabindex]')]
            .filter((element) => ! element.disabled && element.tabIndex >= 0 && element.checkVisibility());
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (! first) {
            return;
        }

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (! event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    const outside = (event) => {
        const box = dialog.getBoundingClientRect();

        return event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom;
    };

    dialog.addEventListener('made-cms:pick', (event) => {
        request = event.detail ?? {};
        chosen = request.file ?? null;
        ui.for.textContent = [format(texts.for, { field: request.field ?? '', place: request.place ?? '' }), request.hint]
            .filter(Boolean)
            .join(' · ');
        draw();

        if (! dialog.open) {
            dialog.showModal();
        }

        browser.dispatchEvent(new CustomEvent('made-cms:media-load', { detail: { file: chosen, many: request.many ?? null } }));
        search?.focus();
    });

    browser.addEventListener('made-cms:media-chosen', (event) => {
        chosen = event.detail?.file ?? null;
        draw();
    });

    browser.addEventListener('made-cms:media-selection', (event) => {
        selection = {
            selecting: event.detail?.selecting === true,
            files: [...(event.detail?.files ?? [])],
            full: event.detail?.full === true,
        };
        draw();
    });

    for (const field of uploads) {
        field.addEventListener('made-cms:upload', (event) => {
            if (event.detail?.sending === true) {
                sending.add(field);
            } else {
                sending.delete(field);
            }
        });
    }

    /** Stops the bytes that still travel: a file the server is storing already lands in Media. */
    function stop() {
        for (const field of sending) {
            field.dispatchEvent(new CustomEvent('made-cms:upload-stop'));
        }
    }

    /** Finishes both upload fields; true unless one kept an alt text the server refused. */
    async function finish() {
        finishing = true;

        try {
            return (await Promise.all(uploads.map(finished))).every(Boolean);
        } finally {
            finishing = false;
        }
    }

    /**
     * "Deze gebruiken": the upload field finishes first, so the choice holds the alt text the editor typed for a new
     * photo (made-cms:media-chosen and made-cms:media-selection follow made-cms:upload-closed). An alt text the server
     * refused keeps the dialog open. The side panel gets the files in the order of the items they become.
     */
    async function use() {
        if (picked().length === 0 || ! request || finishing || ! await finish() || picked().length === 0 || ! request) {
            return;
        }

        const files = picked();
        const choose = request.choose;

        dialog.close();
        choose?.(files);
    }

    /** Leaves without a choice: a photo whose bytes still travel stops, a typed alt text is stored, then it closes. */
    async function leave() {
        if (finishing) {
            return;
        }

        stop();

        if (await finish() && dialog.open) {
            dialog.close();
        }
    }

    ui.use.addEventListener('click', use);
    ui.cancel.addEventListener('click', leave);
    ui.close.addEventListener('click', leave);

    // Escape leaves like "Annuleren", after the upload field finished.
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        leave();
    });

    // A click on the dimmed page beside the dialog closes it, like "Annuleren".
    dialog.addEventListener('pointerdown', (event) => {
        downOutside = outside(event);
    });
    dialog.addEventListener('click', (event) => {
        if (downOutside && outside(event)) {
            leave();
        }

        downOutside = false;
    });

    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'Tab') {
            wrap(event);
        }
    });

    // However it closed (the browser may close it on a second Escape without asking): a photo whose bytes still travel
    // stops, one the server is storing lands in Media, and a typed alt text is stored.
    dialog.addEventListener('close', () => {
        stop();
        uploads.forEach(finished);

        const opener = request?.opener ?? null;

        request = null;

        if (opener?.isConnected) {
            opener.focus();
        }
    });
}

document.querySelectorAll('[data-media]').forEach(browse);
document.querySelectorAll('[data-media-picker]').forEach(pick);
