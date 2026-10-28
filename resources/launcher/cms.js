/*
 * Made CMS pages: the menu behind the button in the top-left corner.
 * It peeks open once when a page loads, until the editor clicks the button for the first time; after that
 * it stays as the editor left it. The state lives on <html data-menu>, set before the first paint by the
 * layout; without this script the menu simply stays open. All copy comes from the markup.
 * Timings follow the design "Made CMS — Inloggen" (7.6).
 */

const PEEK_AFTER = 700;
const PEEK_CLOSE_AFTER = 2200;
const MENU_KEY = 'made-cms-menu';
const FOUND_KEY = 'made-cms-menu-found';
const NARROW = '(max-width: 720px)';

const root = document.documentElement;
const button = document.querySelector('.menuknop');
const menu = document.getElementById('made-cms-menu');

/* Storage can be blocked or full; the menu then simply forgets. */
function remember(key, value) {
    try {
        localStorage.setItem(key, value);
    } catch {
        // Nothing to remember it in.
    }
}

function isNarrow() {
    return window.matchMedia(NARROW).matches;
}

function show(state) {
    const open = state === 'open';

    root.dataset.menu = state;
    button.setAttribute('aria-expanded', String(open));
    button.setAttribute('aria-label', open ? button.dataset.labelClose : button.dataset.labelOpen);
    menu.inert = !open;
}

function found() {
    return 'menuFound' in root.dataset;
}

if (button && menu) {
    const timers = [];
    const stopPeeking = () => timers.splice(0).forEach(clearTimeout);

    show(root.dataset.menu === 'open' ? 'open' : 'dicht');

    if (!found() && root.dataset.menu === 'dicht') {
        timers.push(setTimeout(() => root.dataset.menu === 'dicht' && show('kier'), PEEK_AFTER));
        timers.push(setTimeout(() => root.dataset.menu === 'kier' && show('dicht'), PEEK_CLOSE_AFTER));
    }

    const peek = () => {
        if (!found() && root.dataset.menu === 'dicht') {
            show('kier');
        }
    };

    const unpeek = () => {
        if (!found() && root.dataset.menu === 'kier') {
            show('dicht');
        }
    };

    button.addEventListener('mouseenter', peek);
    button.addEventListener('focus', peek);
    button.addEventListener('mouseleave', unpeek);
    button.addEventListener('blur', unpeek);

    button.addEventListener('click', () => {
        stopPeeking();

        root.dataset.menuFound = '';
        remember(FOUND_KEY, '1');

        const next = root.dataset.menu === 'open' ? 'dicht' : 'open';

        show(next);
        remember(MENU_KEY, next);

        if (next === 'open' && isNarrow()) {
            menu.querySelector('a')?.focus();
        }
    });

    /* On a phone the open menu lies over the page: Escape or a tap next to it closes it. */
    const closeOverlay = (focusButton) => {
        if (root.dataset.menu !== 'open' || !isNarrow()) {
            return;
        }

        show('dicht');
        remember(MENU_KEY, 'dicht');

        if (focusButton) {
            button.focus();
        }
    };

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeOverlay(true);
        }
    });

    document.querySelector('.hoofd')?.addEventListener('click', () => closeOverlay(false));
}
