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
 * What a host hears on the list (the events bubble):
 * - made-cms:media-choosing {file, wait(promise)} before the choice changes, cancelable: a host that cancels it keeps
 *   the choice, and one that hands wait() a promise holds it until the promise resolved (false keeps it);
 * - made-cms:media-chosen {file} when the choice changes, the file as the upload field gets one;
 * - made-cms:media-folder {folder} when the editor opens a folder: {id, name}, or null for "Alle bestanden".
 * A host loads the list with made-cms:media-load {file} on it: the folder it was in, no search, newest first, with
 * that file chosen. A list outside a dialog loads at once.
 *
 * The picker ([data-media-picker]) is a modal <dialog> around a list. The side panel opens it with made-cms:pick on the
 * dialog, {field, place, hint, file, opener, choose(file)}; "Deze gebruiken" closes it and calls choose with the chosen
 * file. Escape, "Annuleren", the cross and a click beside it close it without a choice; the focus goes back to opener.
 * Both finish the upload field first, so an alt text the editor typed for a new photo is stored and goes with the
 * choice, and the field is at rest the next time the picker opens.
 */

const HEADERS = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

/** How long the editor pauses typing before the list searches, in ms. */
const SEARCH_AFTER = 250;

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
    } : null;
    const folderTemplate = root.querySelector('template[data-media-folder]');
    const tileTemplate = root.querySelector('template[data-media-tile]');
    const all = ui.folders?.querySelector('button[data-folder=""]');
    const upload = root.querySelector('[data-upload]');

    if (Object.values(ui).some((element) => element === null) || ! folderTemplate || ! tileTemplate || ! all) {
        return;
    }

    if (shelf && Object.values(shelf).some((element) => element === null)) {
        return;
    }

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
            ui.folders.append(item);
        }

        count(data.counts);
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
        pressFolders();
        trail();
        upload?.dispatchEvent(new CustomEvent('made-cms:upload-folder', { detail: { folder } }));
        tell('made-cms:media-folder', { folder });

        if (library) {
            refresh();

            return;
        }

        load().then(remember);
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
        const chosen = state.chosen?.id === file.id;

        tile.setAttribute('aria-pressed', String(chosen));
        tile.classList.toggle('gekozen', chosen);
        // In the picker the check of the choice takes the place of the dot; the library keeps it.
        slot('tile-no-alt', tile).hidden = (chosen && ! library) || file.kind !== 'image' || (file.alt ?? '') !== '';
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
        tile.addEventListener('click', () => {
            if (state.chosen?.id !== file.id) {
                choose(state.files.find((candidate) => candidate.id === file.id) ?? file);
            }
        });
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

    /** Draws a page of the list as its endpoint answers it. */
    function draw(data, more = false) {
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

        state.loading = controller;
        root.dataset.loading = '';
        ui.failed.hidden = true;

        try {
            const response = await fetch(url(settings.urls.files, {
                folder: state.folder?.id,
                q: search(),
                kind: kind(),
                without_alt: withoutAlt() ? 1 : null,
                sort: pressed(ui.newest) ? 'newest' : 'name',
                page,
            }), { credentials: 'same-origin', headers: HEADERS, signal: controller.signal });
            const data = await response.json().catch(() => null);

            if (! response.ok || ! Array.isArray(data?.files)) {
                // The folder left the library: the folders take the list back to all files.
                if (response.status === 422 && data?.errors?.folder) {
                    folders();
                }

                fail(response.status === 401 || response.status === 419 ? texts.expired : texts.failed);

                return false;
            }

            const tiles = draw(data, more);

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

    upload?.addEventListener('made-cms:upload', (event) => {
        state.uploading = event.detail?.busy === true || (event.detail?.total ?? 0) > 0;
        show();
    });

    // A file uploaded in the picker is the choice, at the top of the list it is in. The library keeps its filter, search
    // and order, and shows a new file where they place it, without choosing it: a batch would make the side column
    // jump, and an editor working through a filter keeps it. The upload field shows every file while it comes in.
    upload?.addEventListener('made-cms:uploaded', (event) => {
        const file = event.detail?.file;

        if (! file) {
            return;
        }

        if (library) {
            window.clearTimeout(refreshing);
            refreshing = window.setTimeout(refresh, 150);

            return;
        }

        reset();
        choose(file);
        load();
        folders();
    });

    // The alt text the editor gave the new file, and its name and crop from the crop step, as they are stored.
    upload?.addEventListener('made-cms:upload-closed', (event) => {
        for (const file of event.detail?.files ?? []) {
            replace(file);
        }

        repaint();

        if (library) {
            folders();
        }
    });

    /** A file as it is stored now, in place of the one the list holds. */
    function replace(file) {
        state.files = state.files.map((candidate) => (candidate.id === file.id ? file : candidate));
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

    root.addEventListener('made-cms:media-load', (event) => {
        // A photo the server was still storing when the list was left asks no alt text any more: it is "later".
        finished(upload);
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
    const upload = browser.querySelector('[data-upload]');
    const search = browser.querySelector('[data-slot="media-search"]');

    if (Object.values(ui).some((element) => element === null)) {
        return;
    }

    let request = null;
    let chosen = null;
    let sending = false;
    let downOutside = false;
    // Waiting for the upload field to finish, so a second click does nothing.
    let finishing = false;

    function draw() {
        const alt = chosen?.alt ?? '';

        ui.name.textContent = chosen ? chosen.name : texts.nothing;
        ui.alt.textContent = chosen ? (alt === '' ? texts.no_alt : format(texts.alt, { alt })) : texts.nothing_hint;
        ui.alt.dataset.alt = chosen ? (alt === '' ? 'missing' : 'set') : '';
        ui.thumb.hidden = ! chosen?.image;

        const small = chosen?.thumb ?? chosen?.url;

        if (chosen?.image && ui.thumb.getAttribute('src') !== small) {
            ui.thumb.src = small;
        }

        ui.use.textContent = chosen ? texts.use : texts.choose_first;
        ui.use.setAttribute('aria-disabled', String(! chosen));
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

        browser.dispatchEvent(new CustomEvent('made-cms:media-load', { detail: { file: chosen } }));
        search?.focus();
    });

    browser.addEventListener('made-cms:media-chosen', (event) => {
        chosen = event.detail?.file ?? null;
        draw();
    });

    upload?.addEventListener('made-cms:upload', (event) => {
        sending = event.detail?.sending === true;
    });

    async function finish() {
        finishing = true;

        try {
            return await finished(upload);
        } finally {
            finishing = false;
        }
    }

    /**
     * "Deze gebruiken": the upload field finishes first, so the choice holds the alt text the editor typed for a new
     * photo (made-cms:media-chosen follows made-cms:upload-closed). An alt text the server refused keeps the dialog open.
     */
    async function use() {
        if (! chosen || ! request || finishing || ! await finish() || ! chosen || ! request) {
            return;
        }

        const file = chosen;
        const choose = request.choose;

        dialog.close();
        choose?.(file);
    }

    /** Leaves without a choice: a photo whose bytes still travel stops, a typed alt text is stored, then it closes. */
    async function leave() {
        if (finishing) {
            return;
        }

        if (sending) {
            upload?.dispatchEvent(new CustomEvent('made-cms:upload-stop'));
        }

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
        if (sending) {
            upload?.dispatchEvent(new CustomEvent('made-cms:upload-stop'));
        }

        finished(upload);

        const opener = request?.opener ?? null;

        request = null;

        if (opener?.isConnected) {
            opener.focus();
        }
    });
}

document.querySelectorAll('[data-media]').forEach(browse);
document.querySelectorAll('[data-media-picker]').forEach(pick);
