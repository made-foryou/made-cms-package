/*
 * The edit shell: the page of the site in a frame of the same origin, with an outline over every strip.
 *
 * The markup, the outline and every text come from the server (cms/edit.blade.php and lang/);
 * this file only adds behaviour. It never changes the site itself: the outlines live in a shadow root
 * of one element of its own, so the css of the site and of the outlines never touch each other.
 */

const HOST = 'made-cms-outlines';
const ATTRIBUTE = 'data-cms-strip';

// A site rendered in the browser puts its strips in the page after it loaded; only a strip that is still
// missing once the page has been quiet this long counts as a view without the attribute.
const WARN_AFTER = 400;

const shell = document.querySelector('[data-edit-shell]');
const frame = shell?.querySelector('iframe.bewerkframe');
const overlay = document.querySelector('template[data-overlay]');
const config = JSON.parse(document.querySelector('script[data-config]')?.textContent ?? '{}');
const strips = new Map((config.strips ?? []).map((strip) => [strip.id, strip]));
const texts = config.texts ?? {};

const panel = {
    choose: shell?.querySelector('[data-slot="choose"]'),
    strip: shell?.querySelector('[data-slot="strip"]'),
    name: shell?.querySelector('[data-slot="name"]'),
    position: shell?.querySelector('[data-slot="position"]'),
};

// The chosen strip outlives the document in the frame, so it stays chosen when the frame reloads.
let selected = null;
let outlines = null;

/* Fills :placeholders like Laravel does: the longest name first, so :id never eats into :identifier. */
function format(text, replace) {
    return Object.keys(replace)
        .sort((a, b) => b.length - a.length)
        .reduce((result, key) => result.replaceAll(':' + key, String(replace[key])), text ?? '');
}

function labelOf(id) {
    return strips.get(id)?.label ?? texts.unknown ?? '';
}

class Outlines {
    constructor(doc) {
        this.doc = doc;
        this.win = doc.defaultView;
        this.elements = [];
        this.buttons = new Map();
        this.warned = new Set();
        this.frameRequest = null;
        this.warnTimer = null;

        this.host = doc.createElement(HOST);
        this.root = this.host.attachShadow({ mode: 'open' });

        const content = overlay.content.cloneNode(true);
        this.template = content.querySelector('.omlijning');
        this.template.remove();
        this.root.append(content);

        this.resizes = new this.win.ResizeObserver(() => this.schedule());
        this.mutations = new this.win.MutationObserver((records) => {
            if (records.some((record) => this.concernsSite(record))) {
                this.scan();
            }
        });

        // Following a link or sending a form would leave the edit mode; another page opens from the CMS.
        doc.addEventListener('click', (event) => {
            if (event.target instanceof this.win.Element && event.target.closest('a[href]')) {
                event.preventDefault();
            }
        }, true);
        doc.addEventListener('submit', (event) => event.preventDefault(), true);
        this.win.addEventListener('resize', () => this.schedule());

        this.mutations.observe(doc.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: [ATTRIBUTE] });
        this.resizes.observe(doc.documentElement);
        this.scan();
    }

    /* Placing the outlines adds the host to the page; that alone is no reason to look again. */
    concernsSite(record) {
        if (record.type === 'attributes') {
            return true;
        }

        return [...record.addedNodes, ...record.removedNodes].some((node) => node !== this.host);
    }

    scan() {
        if (! this.host.isConnected) {
            this.doc.documentElement.append(this.host);
        }

        const elements = [...this.doc.querySelectorAll('[' + ATTRIBUTE + ']')];

        for (const [element, button] of this.buttons) {
            if (! elements.includes(element)) {
                button.remove();
                this.resizes.unobserve(element);
                this.buttons.delete(element);
            }
        }

        elements.forEach((element, index) => {
            let button = this.buttons.get(element);

            if (! button) {
                button = this.template.cloneNode(true);
                button.addEventListener('click', () => select(button.dataset.strip));
                this.buttons.set(element, button);
                this.resizes.observe(element);
            }

            const id = element.getAttribute(ATTRIBUTE);

            button.dataset.strip = id;
            button.querySelector('.naam').textContent = labelOf(id);
            button.querySelector('.telling').textContent = strips.get(id)?.fields ?? '';
            button.setAttribute('aria-label', format(texts.position, { n: index + 1, m: elements.length }) + ': ' + labelOf(id));
            button.setAttribute('aria-pressed', String(id === selected));
            button.classList.toggle('actief', id === selected);

            // Appending moves a button that is already there, so the outlines follow the order of the strips.
            this.root.append(button);
        });

        this.elements = elements;
        this.place();
        showPanel();
        this.warnLater();
    }

    schedule() {
        if (this.frameRequest === null) {
            this.frameRequest = this.win.requestAnimationFrame(() => {
                this.frameRequest = null;
                this.place();
            });
        }
    }

    /* Each outline lies over its strip in the coordinates of the document, so it scrolls along with the site. */
    place() {
        for (const [element, button] of this.buttons) {
            const box = element.getBoundingClientRect();

            button.hidden = box.width === 0 && box.height === 0;
            button.style.top = (box.top + this.win.scrollY) + 'px';
            button.style.left = (box.left + this.win.scrollX) + 'px';
            button.style.width = box.width + 'px';
            button.style.height = box.height + 'px';
        }
    }

    positionOf(id) {
        const index = this.elements.findIndex((element) => element.getAttribute(ATTRIBUTE) === id);

        return index < 0 ? null : { n: index + 1, m: this.elements.length };
    }

    /* A visible strip of the page that the site shows without the attribute cannot be chosen: tell the developer. */
    warnLater() {
        this.win.clearTimeout(this.warnTimer);
        this.warnTimer = this.win.setTimeout(() => {
            for (const strip of strips.values()) {
                if (strip.visible && ! this.warned.has(strip.id) && this.positionOf(strip.id) === null) {
                    this.warned.add(strip.id);
                    console.warn(format(texts.missing, { id: strip.id, type: strip.type }));
                }
            }
        }, WARN_AFTER);
    }
}

function select(id) {
    selected = id;

    for (const button of outlines?.buttons.values() ?? []) {
        button.classList.toggle('actief', button.dataset.strip === id);
        button.setAttribute('aria-pressed', String(button.dataset.strip === id));
    }

    showPanel();
}

/*
 * The panel names the chosen strip and where it is on the page. While the frame reloads the strip is briefly
 * not there; the panel then keeps what it showed, and the outline is chosen again once the strip is back.
 */
function showPanel() {
    const position = selected === null ? null : outlines?.positionOf(selected);

    if (! position) {
        return;
    }

    panel.choose.hidden = true;
    panel.strip.hidden = false;
    panel.name.textContent = labelOf(selected);
    panel.position.textContent = format(texts.position, position);
    shell.dataset.selected = selected;
}

function attach() {
    const doc = frame.contentDocument;

    // Only a document of the same origin can be read; a blank frame is the moment before the site arrives.
    if (! doc || doc === outlines?.doc || doc.location.href === 'about:blank') {
        return;
    }

    outlines = new Outlines(doc);
}

if (frame && overlay) {
    frame.addEventListener('load', attach);

    if (frame.contentDocument?.readyState === 'complete') {
        attach();
    }
}
