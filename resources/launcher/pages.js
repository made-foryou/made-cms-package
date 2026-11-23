// The tree of the pages screen (design 15.1): moving a page by dragging its grip or with the buttons of its row.
//
// One request per move (PUT …/position, MovePage). The script never draws the tree itself: after every answer, also a
// refusal, it reads the screen again and takes the tree and the menu from it, so the server's markup is the only one
// and the tree always shows how the pages are stored.
//
// Searching and filtering are plain forms that work without this script; it takes them over to read the screen again
// at their address and take the parts that follow the pages from it, so the search field keeps the text and the focus.
// A searched or filtered tree moves nothing: the server leaves out its grips and buttons.
//
// The rules of a place live once, in place(): the buttons and dragging both ask it. They are the tree's own, not the
// server's: nothing goes before or under the home page, and the tree hangs no page deeper than config.depth levels
// ("Ligt onder" on the page itself and MovePage go deeper).

const host = document.querySelector('[data-pages]');
const list = () => host?.querySelector('[data-tree]') ?? null;

if (host !== null) {
    const config = JSON.parse(host.dataset.config ?? '{}');
    const texts = config.texts ?? {};
    const limit = Number(config.depth ?? 3);
    const slot = (name) => host.querySelector('[data-slot="' + name + '"]');
    const EDGE = 64;

    let busy = false;
    let dragging = null;

    /* Fills :placeholders like Laravel does: the longest name first, so :n never eats into :name. */
    const format = (text, replace = {}) => Object.keys(replace)
        .sort((a, b) => b.length - a.length)
        .reduce((result, key) => result.replaceAll(':' + key, String(replace[key])), text ?? '');

    function announce(text) {
        const region = slot('tree-announce');

        region.textContent = '';
        window.setTimeout(() => {
            region.textContent = text;
        }, 50);
    }

    /* The message above the tree when a move did not work; it stays until it is closed or the next move. */
    function notice(text) {
        const shelf = slot('tree-notice');

        if (text === null) {
            shelf.replaceChildren();

            return;
        }

        const box = host.querySelector('template[data-tree-notice]').content.firstElementChild.cloneNode(true);

        box.querySelector('[data-slot="tree-notice-text"]').textContent = text;
        box.querySelector('[data-slot="tree-notice-close"]').addEventListener('click', () => notice(null));
        shelf.replaceChildren(box);
    }

    /* ---------------------------------------------------------------------------------------------------------------
     * The rows and their places
     * ------------------------------------------------------------------------------------------------------------ */

    /* The rows of the tree; none while a search finds nothing. */
    function rows() {
        return [...(list()?.querySelectorAll(':scope > li.boomrij') ?? [])].map((el) => ({
            el,
            id: Number(el.dataset.page),
            parent: el.dataset.parent === '' ? null : Number(el.dataset.parent),
            depth: Number(el.dataset.depth),
            home: el.hasAttribute('data-home'),
            title: el.querySelector('.boomrij-naam')?.textContent.trim() ?? '',
            path: el.querySelector('.boomrij-pad')?.textContent.trim() ?? '',
        }));
    }

    /* The page with the pages under it, the rows without them, and how many levels the branch reaches below the page. */
    function branch(all, row) {
        const start = all.indexOf(row);
        let end = start + 1;

        while (end < all.length && all[end].depth > row.depth) {
            end++;
        }

        const own = all.slice(start, end);

        return {
            own,
            others: [...all.slice(0, start), ...all.slice(end)],
            height: Math.max(...own.map((item) => item.depth)) - row.depth,
        };
    }

    const siblingsOf = (all, row) => all.filter((item) => item.parent === row.parent);

    /* Where the branch of others starting at a row ends: the gap after it. */
    function after(others, row) {
        let gap = others.indexOf(row) + 1;

        while (gap < others.length && others[gap].depth > row.depth) {
            gap++;
        }

        return gap;
    }

    /* The depths a page can take in a gap between two rows, in the order of the tree. */
    function range(others, gap) {
        const previous = others[gap - 1] ?? null;
        const next = others[gap] ?? null;

        return { min: next === null ? 0 : next.depth, max: previous === null ? 0 : previous.depth + 1 };
    }

    /*
     * The place a page gets in a gap of the tree without its branch, at a depth: its parent and its place among the
     * pages there, as MovePage counts it (every sibling, the page itself left out). A place the tree does not offer
     * comes back as a reason; same is the place the page has already.
     */
    function place(all, row, gap, depth) {
        const { others, height } = branch(all, row);
        const { min, max } = range(others, gap);

        if (depth < min || depth > max) {
            return { reason: null };
        }

        let parent = null;

        for (let index = gap - 1; depth > 0 && index >= 0; index--) {
            if (others[index].depth === depth - 1) {
                parent = others[index];
                break;
            }
        }

        const parentId = parent === null ? null : parent.id;
        const home = others.findIndex((item) => item.home && item.depth === 0);

        if (depth === 0 && home >= gap) {
            return { reason: 'after_home' };
        }

        if (parent !== null && parent.home && row.parent !== parent.id) {
            return { reason: 'under_home' };
        }

        if (depth > row.depth && depth + height > limit - 1) {
            return { reason: 'too_deep' };
        }

        const position = others.slice(0, gap).filter((item) => item.parent === parentId && item.depth === depth).length;
        const current = siblingsOf(all, row).indexOf(row);

        return { parentId, parent, position, depth, same: parentId === row.parent && position === current };
    }

    /* The place each button of a row moves its page to, or the reason it cannot. */
    function target(all, row, direction) {
        const { others } = branch(all, row);
        const siblings = siblingsOf(all, row);
        const index = siblings.indexOf(row);

        switch (direction) {
            case 'up':
                return index === 0 ? { reason: 'first' } : place(all, row, others.indexOf(siblings[index - 1]), row.depth);
            case 'down':
                return index === siblings.length - 1 ? { reason: 'last' } : place(all, row, after(others, siblings[index + 1]), row.depth);
            case 'indent':
                return index === 0 ? { reason: 'no_previous' } : place(all, row, after(others, siblings[index - 1]), row.depth + 1);
            default: {
                const parent = all.find((item) => item.id === row.parent);

                return parent === undefined ? { reason: 'top' } : place(all, row, after(others, parent), row.depth - 1);
            }
        }
    }

    function reason(key) {
        return format(texts[key] ?? '', { levels: limit });
    }

    /* Every button says whether it can move its page now, and why not. */
    function arm() {
        const all = rows();

        for (const row of all) {
            for (const button of row.el.querySelectorAll('[data-move-to]')) {
                button.dataset.label ??= button.getAttribute('aria-label') ?? '';

                const result = target(all, row, button.dataset.moveTo);
                const refused = result.reason !== undefined;

                button.setAttribute('aria-disabled', refused ? 'true' : 'false');
                button.setAttribute('aria-label', refused && result.reason !== null
                    ? format(texts.disabled, { label: button.dataset.label, reason: reason(result.reason) })
                    : button.dataset.label);
            }
        }
    }

    /* ---------------------------------------------------------------------------------------------------------------
     * Moving
     * ------------------------------------------------------------------------------------------------------------ */

    async function request(url, body) {
        const response = await fetch(url, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': config.csrf_token ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });

        let data = null;

        try {
            data = await response.json();
        } catch {
            data = null;
        }

        return { status: response.status, data };
    }

    function problem(status, data) {
        if (status === 401 || status === 419) {
            return texts.expired;
        }

        const errors = Object.values(data?.errors ?? {});

        return status === 422 && errors.length > 0 ? String([errors[0]].flat()[0]) : texts.failed;
    }

    /* Takes every part of the screen that follows the pages (data-swap: the cards, the filters, the list) and the items
     * of the menu from a screen read again. */
    function swap(page) {
        for (const part of document.querySelectorAll('[data-swap]')) {
            const fresh = page.querySelector('[data-swap="' + part.dataset.swap + '"]');

            if (fresh !== null) {
                part.replaceWith(document.adoptNode(fresh));
            }
        }

        const menu = page.querySelector('#made-cms-menu');

        if (menu !== null) {
            document.querySelector('#made-cms-menu')?.replaceChildren(...[...menu.childNodes].map((node) => document.adoptNode(node)));
        }

        arm();
    }

    /* Reads the screen again at an address; the parsed screen, or null when it could not. */
    async function read(url, signal = undefined) {
        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'text/html' },
                signal,
            });
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');

            return response.ok && page.querySelector('[data-pages]') !== null ? page : null;
        } catch {
            return null;
        }
    }

    /* Reads the screen again and takes the parts that follow the pages from it; false when it could not. */
    async function reload() {
        const page = await read(window.location.href);

        if (page === null) {
            return false;
        }

        swap(page);

        return true;
    }

    /* What the screen reader says once the page is in its new place, read from the tree as the server drew it. */
    function told(id, before) {
        const all = rows();
        const row = all.find((item) => item.id === id);

        if (row === undefined) {
            return '';
        }

        const siblings = siblingsOf(all, row);
        const replace = { title: row.title, n: siblings.indexOf(row) + 1, m: siblings.length, path: row.path };

        if (row.parent === before) {
            return format(texts.moved, replace);
        }

        const parent = all.find((item) => item.id === row.parent);

        return parent === undefined
            ? format(texts.moved_top, replace)
            : format(texts.moved_under, { ...replace, parent: parent.title });
    }

    async function move(row, to, focus = null) {
        if (busy || to.same) {
            return;
        }

        busy = true;
        host.setAttribute('aria-busy', 'true');
        notice(null);

        const { status, data } = await request(row.el.dataset.move, { parent_id: to.parentId, position: to.position })
            .catch(() => ({ status: 0, data: null }));
        const reloaded = await reload();

        busy = false;
        host.removeAttribute('aria-busy');

        if (focus !== null) {
            list()?.querySelector('[data-page="' + row.id + '"] [data-move-to="' + focus + '"]')?.focus();
        }

        if (status !== 204) {
            notice(problem(status, data));

            return;
        }

        if (! reloaded) {
            notice(texts.failed);

            return;
        }

        announce(told(row.id, row.parent));
    }

    host.addEventListener('click', (event) => {
        const button = event.target.closest('[data-move-to]');

        if (button === null || ! list()?.contains(button) || button.getAttribute('aria-disabled') === 'true') {
            return;
        }

        const all = rows();
        const row = all.find((item) => item.el.contains(button));
        const to = target(all, row, button.dataset.moveTo);

        if (to.reason === undefined) {
            move(row, to, button.dataset.moveTo);
        }
    });

    /* ---------------------------------------------------------------------------------------------------------------
     * Searching and filtering in place
     * ------------------------------------------------------------------------------------------------------------ */

    const search = host.querySelector('[data-pages-search]');
    const field = search?.querySelector('input[name="q"]') ?? null;
    const WAIT = 250;
    let generation = 0;
    let reading = null;
    let typing = 0;

    const pressed = () => host.querySelector('[data-filter][aria-pressed="true"]')?.dataset.filter ?? 'all';

    /* The address of the screen for the text in the field and a filter, without what is the default. */
    function address(filter) {
        const url = new URL(search.action, window.location.href);
        const text = field.value.trim();

        if (text !== '') {
            url.searchParams.set('q', text);
        }

        if (filter !== 'all') {
            url.searchParams.set('filter', filter);
        }

        return url;
    }

    /* The search form keeps the filter, so Enter in the field without this script searches within it too. */
    function keep(filter) {
        let hidden = search.querySelector('input[name="filter"]');

        if (filter === 'all') {
            hidden?.remove();

            return;
        }

        if (hidden === null) {
            hidden = Object.assign(document.createElement('input'), { type: 'hidden', name: 'filter' });
            search.append(hidden);
        }

        hidden.value = filter;
    }

    /* Shows the screen at an address: only the newest answer counts, and one that cannot be read becomes a visit. */
    async function refresh(url, filter, focus) {
        const mine = ++generation;

        reading?.abort();
        reading = new AbortController();
        host.setAttribute('aria-busy', 'true');

        const page = await read(url.toString(), reading.signal);

        if (mine !== generation) {
            return;
        }

        host.removeAttribute('aria-busy');

        if (page === null) {
            window.location.assign(url.toString());

            return;
        }

        swap(page);
        keep(filter);
        window.history.replaceState(window.history.state, '', url.pathname + url.search + url.hash);

        if (focus) {
            host.querySelector('[data-filter="' + filter + '"]')?.focus();
        }

        announce(slot('pages-found')?.textContent.trim() ?? '');
    }

    if (search !== null && field !== null) {
        search.addEventListener('submit', (event) => {
            event.preventDefault();
            window.clearTimeout(typing);
            refresh(address(pressed()), pressed(), false);
        });

        field.addEventListener('input', () => {
            window.clearTimeout(typing);
            typing = window.setTimeout(() => refresh(address(pressed()), pressed(), false), WAIT);
        });

        host.addEventListener('submit', (event) => {
            const button = event.submitter;

            if (button?.dataset.filter === undefined || event.target === search) {
                return;
            }

            event.preventDefault();
            window.clearTimeout(typing);
            refresh(address(button.dataset.filter), button.dataset.filter, true);
        });
    }

    /* ---------------------------------------------------------------------------------------------------------------
     * Dragging: the grip takes the page with the pages under it. Up and down choose the gap between two rows, right
     * and left the depth there; a line shows where it lands. Escape or a gap the tree does not offer drops nothing.
     * ------------------------------------------------------------------------------------------------------------ */

    function indent() {
        return parseFloat(getComputedStyle(list()).getPropertyValue('--boom-inspring')) || 44;
    }

    /* The gap the pointer is in: before the first row whose middle lies below it. */
    function gapAt(others, y) {
        const index = others.findIndex((item) => {
            const box = item.el.getBoundingClientRect();

            return y < box.top + box.height / 2;
        });

        return index === -1 ? others.length : index;
    }

    /* The place in a gap closest to the depth the pointer asks for, among the depths the tree offers there. */
    function nearest(all, row, others, gap, wanted) {
        const { min, max } = range(others, gap);
        const depths = [];

        for (let depth = min; depth <= max; depth++) {
            depths.push(depth);
        }

        depths.sort((a, b) => Math.abs(a - wanted) - Math.abs(b - wanted) || b - a);

        for (const depth of depths) {
            const to = place(all, row, gap, depth);

            if (to.reason === undefined) {
                return to;
            }
        }

        return null;
    }

    function show(line, others, gap, to) {
        if (to === null) {
            line.remove();

            return;
        }

        const tree = list();
        const next = others[gap] ?? null;
        const previous = others[gap - 1] ?? null;
        const top = next !== null
            ? next.el.offsetTop - 5
            : previous.el.offsetTop + previous.el.offsetHeight + 5;

        line.style.top = top + 'px';
        line.style.setProperty('--plek-diepte', String(to.depth));
        line.querySelector('.boom-plek-label').textContent = to.parent === null
            ? texts.place_top
            : format(texts.place_under, { parent: to.parent.title });

        if (line.parentElement !== tree) {
            tree.append(line);
        }
    }

    function drag(event, grip) {
        if (event.button !== 0 || busy || dragging !== null) {
            return;
        }

        const all = rows();
        const row = all.find((item) => item.el.contains(grip));

        if (row === undefined || row.home) {
            return;
        }

        event.preventDefault();

        const { own, others } = branch(all, row);
        const line = host.querySelector('template[data-tree-place]').content.firstElementChild.cloneNode(true);
        const unit = indent();
        const start = { x: event.clientX, y: event.clientY };
        let pointer = { x: event.clientX, y: event.clientY };
        let moved = false;
        let to = null;
        let scrolling = 0;

        const update = () => {
            const gap = gapAt(others, pointer.y);

            to = nearest(all, row, others, gap, row.depth + Math.round((pointer.x - start.x) / unit));
            show(line, others, gap, to);
        };

        /* Near the top or the bottom of the window the page scrolls along, faster the closer the pointer comes. */
        const scroll = () => {
            const speed = pointer.y < EDGE
                ? -Math.ceil((EDGE - pointer.y) / 4)
                : (pointer.y > window.innerHeight - EDGE ? Math.ceil((pointer.y - window.innerHeight + EDGE) / 4) : 0);

            if (speed === 0) {
                scrolling = 0;

                return;
            }

            window.scrollBy(0, speed);
            update();
            scrolling = window.requestAnimationFrame(scroll);
        };

        const onMove = (moveEvent) => {
            pointer = { x: moveEvent.clientX, y: moveEvent.clientY };

            if (! moved && Math.hypot(pointer.x - start.x, pointer.y - start.y) < 4) {
                return;
            }

            if (! moved) {
                moved = true;
                document.documentElement.dataset.boomSleept = '';
                own.forEach((item) => item.el.classList.add('sleept'));
            }

            update();

            if (scrolling === 0) {
                scrolling = window.requestAnimationFrame(scroll);
            }
        };

        const stop = (drop) => {
            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerup', onUp);
            window.removeEventListener('pointercancel', onCancel);
            window.removeEventListener('keydown', onKey, true);
            window.cancelAnimationFrame(scrolling);
            delete document.documentElement.dataset.boomSleept;
            own.forEach((item) => item.el.classList.remove('sleept'));
            line.remove();
            dragging = null;

            if (drop && moved && to !== null) {
                move(row, to);
            }
        };

        const onUp = () => stop(true);
        const onCancel = () => stop(false);
        const onKey = (keyEvent) => {
            if (keyEvent.key === 'Escape') {
                keyEvent.preventDefault();
                stop(false);
            }
        };

        dragging = row;
        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onUp);
        window.addEventListener('pointercancel', onCancel);
        window.addEventListener('keydown', onKey, true);
    }

    host.addEventListener('pointerdown', (event) => {
        const grip = event.target.closest('.greep');

        if (grip !== null && list()?.contains(grip)) {
            drag(event, grip);
        }
    });

    arm();
}
