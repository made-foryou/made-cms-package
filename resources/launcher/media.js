/*
 * Made CMS: the list of the media library and the picker of an image (design 13.4, 14.4).
 *
 * Every [data-media] is a list: the folders with the number of their files (made-cms/media/folders), the files a page
 * at a time as tiles (made-cms/media/files), a search, the filters and "Meer tonen". Its fixed texts and the templates
 * of a folder and a tile are in its markup (cms/partials/media-browser.blade.php), shared with the media library; its
 * config is JSON on itself (data-config). A tile is a button with aria-pressed: the file it chooses. Opening a folder
 * sends the upload field below the folders into it and remembers it for the session once its files are in. A file
 * uploaded in the list is the choice at once.
 *
 * What a host hears on the list (the events bubble):
 * - made-cms:media-chosen {file} when the choice changes, the file as the upload field gets one;
 * - made-cms:media-folder {folder} when the editor opens a folder: {id, name}, or null for "Alle bestanden".
 * A host loads the list with made-cms:media-load {file} on it: the folder it was in, no search, newest first, with
 * that file chosen. A list outside a dialog loads at once.
 *
 * The picker ([data-media-picker]) is a modal <dialog> around a list. The side panel opens it with made-cms:pick on the
 * dialog, {field, place, hint, file, opener, choose(file)}; "Deze gebruiken" closes it and calls choose with the chosen
 * file. Escape, "Annuleren", the cross and a click beside it close it without a choice; the focus goes back to opener.
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

function browse(root) {
    const settings = configOf(root);

    if (! settings) {
        return;
    }

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
    const folderTemplate = root.querySelector('template[data-media-folder]');
    const tileTemplate = root.querySelector('template[data-media-tile]');
    const all = ui.folders?.querySelector('button[data-folder=""]');
    const upload = root.querySelector('[data-upload]');

    if (Object.values(ui).some((element) => element === null) || ! folderTemplate || ! tileTemplate || ! all) {
        return;
    }

    const state = {
        // The open folder, {id, name}; null for "Alle bestanden".
        folder: settings.folder ?? null,
        // The folder the session holds, so it is only sent when it changes.
        remembered: settings.folder?.id ?? null,
        page: 1,
        files: [],
        chosen: null,
        loading: null,
    };
    let typing = null;

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

    function pressFolders() {
        const open = String(state.folder?.id ?? '');

        ui.folders.querySelectorAll('button[data-folder]').forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.folder === open));
        });
    }

    /** The folders with their numbers. A folder that left the library (someone removed it) takes the list back to all. */
    async function folders() {
        let data;

        try {
            const response = await fetch(url(settings.urls.folders, { kind: settings.kind }), { credentials: 'same-origin', headers: HEADERS });

            data = response.ok ? await response.json() : null;
        } catch {
            data = null;
        }

        if (! Array.isArray(data?.folders)) {
            return;
        }

        slot('folder-count', all).textContent = String(data.all ?? '');
        ui.folders.querySelectorAll('li[data-folder-item]').forEach((item) => item.remove());

        for (const folder of data.folders) {
            const item = folderTemplate.content.firstElementChild.cloneNode(true);
            const button = item.querySelector('button');

            item.dataset.folderItem = '';
            button.dataset.folder = String(folder.id);
            slot('folder-name', item).textContent = folder.name;
            slot('folder-count', item).textContent = String(folder.count);
            button.addEventListener('click', () => open({ id: folder.id, name: folder.name }));
            ui.folders.append(item);
        }

        const current = state.folder === null ? null : data.folders.find((folder) => folder.id === state.folder.id);

        if (state.folder !== null && ! current) {
            open(null);

            return;
        }

        if (current) {
            state.folder = { id: current.id, name: current.name };
        }

        pressFolders();
    }

    /**
     * Opens a folder: the upload field below the folders follows it, the page hears it, and once its files are in the
     * session remembers it. Only while the list is in sight, so in the picker no save of the side panel runs alongside
     * and writes the session back.
     */
    function open(folder) {
        if ((folder?.id ?? null) === (state.folder?.id ?? null)) {
            return;
        }

        state.folder = folder;
        pressFolders();
        upload?.dispatchEvent(new CustomEvent('made-cms:upload-folder', { detail: { folder } }));
        tell('made-cms:media-folder', { folder });
        load().then((loaded) => {
            const dialog = root.closest('dialog');

            if (loaded && (dialog === null || dialog.open)) {
                remember();
            }
        });
    }

    function remember() {
        const id = state.folder?.id ?? null;

        if (id === state.remembered) {
            return;
        }

        state.remembered = id;
        fetch(settings.urls.chosen_folder, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: { ...HEADERS, 'Content-Type': 'application/json', 'X-CSRF-TOKEN': settings.csrf_token ?? '' },
            body: JSON.stringify({ folder: id }),
        }).catch(() => {});
    }

    function paint(tile, file) {
        const chosen = state.chosen?.id === file.id;

        tile.setAttribute('aria-pressed', String(chosen));
        tile.classList.toggle('gekozen', chosen);
        // The check of the choice takes the place of the dot.
        slot('tile-no-alt', tile).hidden = chosen || file.kind !== 'image' || (file.alt ?? '') !== '';
    }

    function tileOf(file) {
        const tile = tileTemplate.content.firstElementChild.cloneNode(true);
        const thumb = slot('tile-thumb', tile);

        tile.dataset.id = String(file.id);

        if (file.image) {
            const focal = file.image.focal ?? { x: 0.5, y: 0.5 };

            thumb.hidden = false;
            thumb.src = file.url;
            thumb.style.objectPosition = (focal.x * 100) + '% ' + (focal.y * 100) + '%';
            // A thumbnail that does not load makes way for the icon.
            thumb.addEventListener('error', () => {
                thumb.hidden = true;
            });
        }

        slot('tile-extension', tile).textContent = file.extension ?? '';
        slot('tile-name', tile).textContent = file.name;
        slot('tile-meta', tile).textContent = file.meta ?? '';
        tile.addEventListener('click', () => choose(state.files.find((candidate) => candidate.id === file.id) ?? file));
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
        state.chosen = file;
        repaint();
        tell('made-cms:media-chosen', { file });
    }

    function fail(message) {
        ui.failed.textContent = message;
        ui.failed.hidden = false;
        ui.status.textContent = message;
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
                q: ui.search.value.trim(),
                kind: settings.kind,
                without_alt: pressed(ui.withoutAlt) ? 1 : null,
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

            const tiles = data.files.map(tileOf);

            state.page = data.page;
            state.files = more ? [...state.files, ...data.files] : data.files;
            more ? ui.grid.append(...tiles) : ui.grid.replaceChildren(...tiles);
            ui.more.hidden = ! data.more;
            ui.empty.hidden = state.files.length > 0;
            ui.status.textContent = data.summary ?? '';

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

    /** Back to the start of the list: no search, newest first, every image. */
    function reset() {
        window.clearTimeout(typing);
        ui.search.value = '';
        ui.newest.setAttribute('aria-pressed', 'true');
        ui.withoutAlt.setAttribute('aria-pressed', 'false');
    }

    all.addEventListener('click', () => open(null));
    ui.search.addEventListener('input', () => {
        window.clearTimeout(typing);
        typing = window.setTimeout(() => load(), SEARCH_AFTER);
    });
    ui.search.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            window.clearTimeout(typing);
            load();
        }
    });

    for (const toggle of [ui.newest, ui.withoutAlt]) {
        toggle.addEventListener('click', () => {
            toggle.setAttribute('aria-pressed', String(! pressed(toggle)));
            load();
        });
    }

    ui.more.addEventListener('click', () => load(true));

    // A file uploaded here is the choice, at the top of the list it is in.
    upload?.addEventListener('made-cms:uploaded', (event) => {
        const file = event.detail?.file;

        if (! file) {
            return;
        }

        reset();
        choose(file);
        load();
        folders();
    });

    // The alt text the editor gave the new file, as it is stored.
    upload?.addEventListener('made-cms:upload-closed', (event) => {
        for (const file of event.detail?.files ?? []) {
            state.files = state.files.map((candidate) => (candidate.id === file.id ? file : candidate));

            if (state.chosen?.id === file.id) {
                choose(file);
            }
        }

        repaint();
    });

    root.addEventListener('made-cms:media-load', (event) => {
        state.chosen = event.detail?.file ?? null;
        state.files = [];
        reset();
        ui.grid.replaceChildren();
        ui.more.hidden = true;
        ui.empty.hidden = true;
        folders();
        load();
    });

    if (root.closest('dialog') === null) {
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

    function draw() {
        const alt = chosen?.alt ?? '';

        ui.name.textContent = chosen ? chosen.name : texts.nothing;
        ui.alt.textContent = chosen ? (alt === '' ? texts.no_alt : format(texts.alt, { alt })) : texts.nothing_hint;
        ui.alt.dataset.alt = chosen ? (alt === '' ? 'missing' : 'set') : '';
        ui.thumb.hidden = ! chosen?.image;

        if (chosen?.image && ui.thumb.getAttribute('src') !== chosen.url) {
            ui.thumb.src = chosen.url;
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

    ui.use.addEventListener('click', () => {
        if (! chosen || ! request) {
            return;
        }

        const file = chosen;
        const choose = request.choose;

        dialog.close();
        choose?.(file);
    });
    ui.cancel.addEventListener('click', () => dialog.close());
    ui.close.addEventListener('click', () => dialog.close());

    // A click on the dimmed page beside the dialog closes it, like "Annuleren".
    dialog.addEventListener('pointerdown', (event) => {
        downOutside = outside(event);
    });
    dialog.addEventListener('click', (event) => {
        if (downOutside && outside(event)) {
            dialog.close();
        }

        downOutside = false;
    });

    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'Tab') {
            wrap(event);
        }
    });

    // A photo whose bytes still travel stops; one the server is storing lands in Media.
    dialog.addEventListener('close', () => {
        if (sending) {
            upload?.dispatchEvent(new CustomEvent('made-cms:upload-stop'));
        }

        const opener = request?.opener ?? null;

        request = null;

        if (opener?.isConnected) {
            opener.focus();
        }
    });
}

document.querySelectorAll('[data-media]').forEach(browse);
document.querySelectorAll('[data-media-picker]').forEach(pick);
