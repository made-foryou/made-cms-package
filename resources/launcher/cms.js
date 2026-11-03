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

/*
 * The new-page wizard (design 15.3–15.5): one form in two steps, one question per step. Without this script both
 * steps show and the form works the same. The address preview follows the name; the server makes the real address.
 * All copy comes from the markup.
 */

const wizard = document.querySelector('form[data-wizard]');

/* Roughly what the server does with a name: lowercase ascii, words joined by dashes. */
function slugify(text) {
    return text
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

if (wizard) {
    const steps = [...wizard.querySelectorAll('[data-stap]')];
    const title = wizard.elements.namedItem('title');
    const slug = wizard.elements.namedItem('slug');
    const slugField = wizard.querySelector('[data-slug-veld]');
    const slugEdit = wizard.querySelector('[data-slug-edit]');
    const previews = wizard.querySelectorAll('[data-slug-preview]');
    const echo = wizard.querySelector('[data-title-echo]');
    const untitled = echo ? echo.textContent : '';
    const note = wizard.querySelector('[data-stapnoot]');
    let custom = slug.value !== '';

    const sync = () => {
        const value = slugify(custom ? slug.value : title.value);

        previews.forEach((preview) => {
            preview.textContent = value;
        });

        if (echo) {
            echo.textContent = title.value.trim() || untitled;
        }
    };

    const show = (step, focus) => {
        steps.forEach((fieldset) => {
            fieldset.hidden = fieldset.dataset.stap !== String(step);
        });

        wizard.querySelectorAll('[data-balk]').forEach((bar) => {
            bar.classList.toggle('actief', bar.dataset.balk === String(step));
            bar.classList.toggle('gedaan', Number(bar.dataset.balk) < step);
        });

        if (note) {
            note.textContent = note.getAttribute(`data-tekst-${step}`) ?? note.textContent;
        }

        if (focus) {
            steps[step - 1]?.querySelector('input:not([type="hidden"])')?.focus();
        }
    };

    const next = () => {
        if (title.reportValidity()) {
            show(2, true);
        }
    };

    wizard.querySelectorAll('[data-stappen], [data-next], [data-back]').forEach((element) => {
        element.hidden = false;
    });

    if (slugField && slugEdit && !slugField.hasAttribute('data-open')) {
        slugField.hidden = true;
        slugEdit.hidden = false;
    }

    slugEdit?.addEventListener('click', () => {
        slugField.hidden = false;
        slugEdit.hidden = true;
        custom = true;
        slug.value = slug.value || slugify(title.value);
        slug.focus();
        sync();
    });

    title.addEventListener('input', sync);
    slug.addEventListener('input', () => {
        custom = slug.value !== '';
        sync();
    });

    wizard.querySelector('[data-next]')?.addEventListener('click', next);
    wizard.querySelector('[data-back]')?.addEventListener('click', () => show(1, true));

    /* Enter in the first step goes on to the second, instead of creating the page with the first place. */
    steps[0]?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target instanceof HTMLInputElement) {
            event.preventDefault();
            next();
        }
    });

    /* After a refusal the form opens on the step with the error. */
    const failed = wizard.querySelector('.veld.fout, .veld-fout');

    sync();
    show(Number(failed?.closest('[data-stap]')?.dataset.stap ?? 1), false);
}
