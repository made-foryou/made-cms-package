/*
 * Made CMS launcher: the MADE tile in the bottom-right corner and the login panel it opens.
 * The markup arrives server-rendered as a declarative shadow root; this script only adds behaviour.
 * Timings follow the motion spec of the design "Made CMS — Inloggen" (1.9).
 */

const INTRO_KEY = 'made-cms-launcher-intro';
const INTRO_OPEN_AFTER = 900;
const INTRO_CLOSE_AFTER = 4200;
const CLOSE_FALLBACK = 700;
const LATIN = 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD';

let fontsLoaded = false;

/* The server-rendered panel markup, for hosts whose page snapshots lose the shadow root (back/forward in Turbo, Livewire). */
let markup = null;

/*
 * @font-face inside a shadow root is ignored by browsers, so the fonts are added to the document
 * under their own family names; they never collide with the fonts of the host site.
 */
function loadFonts({ fontHeading, fontText }) {
    if (fontsLoaded || typeof FontFace === 'undefined' || !document.fonts) {
        return;
    }

    fontsLoaded = true;

    const faces = [
        ['Made CMS Nunito', fontHeading, '700'],
        ['Made CMS Instrument Sans', fontText, '400 700'],
    ];

    for (const [family, url, weight] of faces) {
        if (!url) {
            continue;
        }

        const face = new FontFace(family, `url("${url}") format("woff2")`, {
            weight,
            style: 'normal',
            display: 'swap',
            unicodeRange: LATIN,
        });

        document.fonts.add(face);
        face.load().catch(() => {});
    }
}

/* Remembers the first visit; without storage the intro stays quiet rather than repeating itself. */
function isFirstVisit() {
    try {
        if (window.localStorage.getItem(INTRO_KEY)) {
            return false;
        }

        window.localStorage.setItem(INTRO_KEY, '1');

        return true;
    } catch {
        return false;
    }
}

class MadeCmsLauncher extends HTMLElement {
    connectedCallback() {
        if (this.started) {
            return;
        }

        const root = this.shadowRoot ?? this.attachTemplate();

        if (!root) {
            return;
        }

        markup ??= root.innerHTML;
        this.started = true;
        this.timers = [];
        this.tile = root.querySelector('.beheer');
        this.dialog = root.querySelector('dialog');
        this.form = root.querySelector('form');
        this.email = root.querySelector('input[type="email"]');

        loadFonts(this.dataset);

        // Escape closes the panel wherever the focus is, also when it left the dialog.
        this.onEscape = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                this.close();
            }
        };

        this.tile.addEventListener('click', () => this.open());
        root.querySelector('.sluit').addEventListener('click', () => this.close());
        this.listenToDialog();
        this.form.addEventListener('submit', (event) => this.submit(event));

        // Only now the tile may show: a tile without working script would be a button that does nothing.
        this.dataset.ready = '';
        this.intro();
    }

    /* Removing an open modal fires no close event, so undo the scroll lock and the Escape listener here. */
    disconnectedCallback() {
        this.timers?.forEach(clearTimeout);

        if (this.dialog?.open || this.scroll) {
            this.reset();
        }
    }

    /* Hosts that swap the body through DOMParser (Turbo, Livewire navigate) never parse declarative shadow roots. */
    attachTemplate() {
        const template = this.querySelector('template[shadowrootmode]');

        if (!template && markup === null) {
            return null;
        }

        const root = this.attachShadow({ mode: 'open', clonable: true });

        if (template) {
            root.append(template.content.cloneNode(true));
            template.remove();
        } else {
            root.innerHTML = markup;
        }

        return root;
    }

    intro() {
        if (!isFirstVisit()) {
            return;
        }

        this.timers.push(
            setTimeout(() => this.tile.classList.add('intro'), INTRO_OPEN_AFTER),
            setTimeout(() => this.tile.classList.remove('intro'), INTRO_CLOSE_AFTER),
        );
    }

    listenToDialog() {
        const dialog = this.dialog;

        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            this.close();
        });

        // Typing an e-mail address must not trigger the host site's keyboard shortcuts.
        for (const type of ['keydown', 'keyup', 'keypress']) {
            dialog.addEventListener(type, (event) => event.stopPropagation());
        }

        // The panel content fills the whole dialog, so only a press on the backdrop targets the dialog itself.
        dialog.addEventListener('pointerdown', (event) => {
            this.pressedBackdrop = event.target === dialog;
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog && this.pressedBackdrop) {
                this.close();
            }

            this.pressedBackdrop = false;
        });

        dialog.addEventListener('close', () => this.closed());
    }

    open() {
        if (this.dialog.open) {
            return;
        }

        this.timers.forEach(clearTimeout);
        this.tile.classList.remove('intro');
        this.dialog.classList.remove('sluitend');
        this.lockScroll();
        document.addEventListener('keydown', this.onEscape, true);
        this.dialog.showModal();
        this.email.focus({ preventScroll: true });
    }

    async close() {
        if (!this.dialog.open || this.closing) {
            return;
        }

        this.closing = true;

        // Closing while the panel still grows: freeze its current size, so it shrinks from there instead of jumping.
        for (const animation of this.dialog.getAnimations()) {
            if (animation.animationName === 'paneel-groei' && animation.playState === 'running') {
                animation.commitStyles();
                animation.cancel();
            }
        }

        this.dialog.classList.add('sluitend');

        const animations = this.dialog.getAnimations({ subtree: true }).map((animation) => animation.finished);

        await Promise.race([
            Promise.allSettled(animations),
            new Promise((resolve) => setTimeout(resolve, CLOSE_FALLBACK)),
        ]);

        this.dialog.close();
    }

    /* Runs after every close, also when the browser closes the dialog without asking. */
    closed() {
        this.reset();
        this.tile.focus({ preventScroll: true });
    }

    reset() {
        this.closing = false;
        document.removeEventListener('keydown', this.onEscape, true);
        this.dialog.classList.remove('sluitend');
        this.dialog.removeAttribute('style');
        this.form.reset();
        this.unlockScroll();
    }

    submit(event) {
        // The next login steps hook in here; until then the panel stays open on the e-mail step.
        event.preventDefault();
    }

    /* Hides the page scrollbar and pads its width, so the site does not shift and the backdrop still covers the full width. */
    lockScroll() {
        const html = document.documentElement;
        const scrollbar = window.innerWidth - html.clientWidth;

        this.scroll = { overflow: html.style.overflow, paddingRight: html.style.paddingRight };

        if (scrollbar > 0) {
            html.style.paddingRight = `${parseFloat(getComputedStyle(html).paddingRight) + scrollbar}px`;
        }

        html.style.overflow = 'hidden';
    }

    unlockScroll() {
        if (!this.scroll) {
            return;
        }

        const style = document.documentElement.style;

        style.overflow = this.scroll.overflow;
        style.paddingRight = this.scroll.paddingRight;
        this.scroll = null;
    }
}

if (!customElements.get('made-cms-launcher')) {
    customElements.define('made-cms-launcher', MadeCmsLauncher);
}
