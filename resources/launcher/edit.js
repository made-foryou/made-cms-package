/*
 * The edit shell: the page of the site in a frame of the same origin, with an outline over every strip.
 *
 * The markup, the outline and every text come from the server (cms/edit.blade.php and lang/);
 * this file only adds behaviour. The outlines live in a shadow root of one element of its own, so the css of
 * the site and of the outlines never touch each other. The site itself only changes where it shows a field with
 * data-cms-field: while the editor types, the new value is patched into that element (made-cms:input from panel.js).
 * A change it cannot show that way reloads the frame after the next save.
 *
 * The strips of the page come from the server in their stored order (config.strips, then made-cms:strips from
 * structure.js after every change of the opbouw), so "Strook n van m" is the place of a strip in that list, also for a
 * hidden strip that is not on the site. Choosing no strip (made-cms:choose with id null) goes back to the opbouw.
 *
 * For whoever chooses the level of a heading (config.heading_levels) it also reads the headings of the page as the
 * frame shows them, after every load, change and patch, and hands them to the shell as made-cms:headings
 * (headings.js). Only the frame knows the real structure: the fixed headings of the site, and the page of a site that
 * renders in the browser.
 */

const HOST = 'made-cms-outlines';
const ATTRIBUTE = 'data-cms-strip';
const FIELD = 'data-cms-field';
const ITEMS = 'data-cms-items';

// A site rendered in the browser puts its strips in the page after it loaded; only a strip that is still
// missing once the page has been quiet this long counts as a view without the attribute.
const WARN_AFTER = 400;

// The headings are read once the page has been quiet this long, so a burst of changes reads them once.
const READ_AFTER = 60;
const LEVELS = 'h1, h2, h3, h4, h5, h6';

const shell = document.querySelector('[data-edit-shell]');
const frame = shell?.querySelector('iframe.bewerkframe');
const overlay = document.querySelector('template[data-overlay]');
const config = JSON.parse(document.querySelector('script[data-config]')?.textContent ?? '{}');
const texts = config.texts ?? {};

// Every strip of the working copy in its stored order, also hidden ones and those of a type the site no longer has.
let order = [];
const strips = new Map();

function useStrips(list) {
    order = (list ?? []).map((strip) => strip.id);
    strips.clear();

    for (const strip of list ?? []) {
        strips.set(strip.id, strip);
    }
}

useStrips(config.strips);

// The chosen strip outlives the document in the frame, so it stays chosen when the frame reloads.
let selected = null;
let outlines = null;

// The last change of every field that the server did not store yet; a reloaded frame gets them again.
const live = new Map();

// Strips with a change the frame could not show, with the number of the last such change: a save that stores it
// reloads the frame.
const stale = new Map();

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
                button.addEventListener('click', (event) => select(button.dataset.strip, null, this.fieldAt(event, button.dataset.strip)));
                this.buttons.set(element, button);
                this.resizes.observe(element);
            }

            const id = element.getAttribute(ATTRIBUTE);
            const position = positionOf(id);

            button.dataset.strip = id;
            button.querySelector('.naam').textContent = labelOf(id);
            button.querySelector('.telling').textContent = strips.get(id)?.fields ?? '';
            button.setAttribute('aria-label', (position ? format(texts.position, position) + ': ' : '') + labelOf(id));
            button.setAttribute('aria-pressed', String(id === selected));
            button.classList.toggle('actief', id === selected);

            // Appending moves a button that is already there, so the outlines follow the order of the strips.
            this.root.append(button);
        });

        this.elements = elements;
        this.place();
        this.warnLater();
        readHeadingsLater();
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

    has(id) {
        return this.elements.some((element) => element.getAttribute(ATTRIBUTE) === id);
    }

    /*
     * The outline lies over the whole strip, so the field the editor clicked is the one under the pointer. The tab
     * with the name, and a click with the keyboard, choose the strip only.
     */
    fieldAt(event, id) {
        if (event.detail === 0 || event.target.closest?.('.tab')) {
            return null;
        }

        const strip = stripElement(this.doc, id);

        if (! strip) {
            return null;
        }

        for (const element of this.doc.elementsFromPoint(event.clientX, event.clientY)) {
            const node = strip.contains(element) ? element.closest('[' + FIELD + '], [' + ITEMS + ']') : null;

            if (node && strip.contains(node)) {
                return pathOf(node, strip);
            }
        }

        return null;
    }

    /* A visible strip of the page that the site shows without the attribute cannot be chosen: tell the developer. */
    warnLater() {
        this.win.clearTimeout(this.warnTimer);
        this.warnTimer = this.win.setTimeout(() => {
            for (const strip of strips.values()) {
                if (strip.visible && strip.known !== false && ! this.warned.has(strip.id) && ! this.has(strip.id)) {
                    this.warned.add(strip.id);
                    console.warn(format(texts.missing, { id: strip.id, type: strip.type }));
                }
            }
        }, WARN_AFTER);
    }
}

/* The place of a strip in the opbouw: the same number the panel and the opbouw use. */
function positionOf(id) {
    const index = order.indexOf(id);

    return index < 0 ? null : { n: index + 1, m: order.length };
}

/* ---------------------------------------------------------------------------------------------------------------
 * Fields on the site. A field is the element with data-cms-field="kop" in the strip. In data-cms-items="knoppen"
 * every direct child is one item, in the order of the list, and data-cms-field in it names a field of that item.
 * ------------------------------------------------------------------------------------------------------------ */

function stripElement(doc, id) {
    return doc.querySelector('[' + ATTRIBUTE + '="' + CSS.escape(id) + '"]');
}

function fieldSelector(key) {
    return '[' + FIELD + '="' + CSS.escape(key) + '"]';
}

/* The list of the strip an element lies in, if any; lists never lie in lists. */
function listOf(node, strip) {
    const list = node.parentElement?.closest('[' + ITEMS + ']') ?? null;

    return list !== null && strip.contains(list) ? list : null;
}

/* The path the panel knows a field by: "kop", "knoppen.1.knop" with the number of items on the site, or "knoppen". */
function pathOf(node, strip) {
    const key = node.getAttribute(FIELD);

    if (key === null) {
        return { path: node.getAttribute(ITEMS), count: null };
    }

    const list = listOf(node, strip);

    if (list === null) {
        return { path: key, count: null };
    }

    const items = [...list.children];
    const index = items.findIndex((item) => item.contains(node));

    return { path: list.getAttribute(ITEMS) + '.' + index + '.' + key, count: items.length };
}

/*
 * The elements that show the field of a change. A field in an item is only found when the site shows as many items
 * as the panel holds: a view that leaves an item out would otherwise get the value of another item.
 */
function nodesFor(doc, input) {
    const strip = stripElement(doc, input.id);
    const [key, index, sub] = input.path.split('.');

    if (! strip) {
        return [];
    }

    if (sub === undefined) {
        return [...strip.querySelectorAll(fieldSelector(key))].filter((node) => listOf(node, strip) === null);
    }

    const nodes = [];

    for (const list of strip.querySelectorAll('[' + ITEMS + '="' + CSS.escape(key) + '"]')) {
        const item = list.children[Number(index)];

        if (list.children.length !== input.count || ! item) {
            return [];
        }

        if (item.matches(fieldSelector(sub))) {
            nodes.push(item);
        }

        nodes.push(...item.querySelectorAll(fieldSelector(sub)));
    }

    return nodes;
}

/* An element that holds nothing but its text: no children, or one text node. */
function holdsText(node) {
    return node.childNodes.length === 0 || (node.childNodes.length === 1 && node.firstChild.nodeType === Node.TEXT_NODE);
}

/*
 * The one text node changes, never the element around it: React and Vue keep a reference to that node and compare
 * what they render with what they rendered before, so they leave the patch alone until the page renders again.
 */
function setText(node, text) {
    if (! holdsText(node)) {
        return false;
    }

    if (node.firstChild === null) {
        node.append(node.ownerDocument.createTextNode(text));
    } else if (node.firstChild.nodeValue !== text) {
        node.firstChild.nodeValue = text;
    }

    return true;
}

/*
 * A long text follows the form the site prints it in: with a <br> per line break (nl2br) when the element holds one,
 * or with the line breaks in the text itself. While the site shows no line break yet that form is unknown, so the
 * first one reloads the frame.
 */
function setLongText(node, text) {
    const children = [...node.childNodes];
    const isBreak = (child) => child.nodeType === Node.ELEMENT_NODE && child.tagName === 'BR';

    if (! children.every((child) => child.nodeType === Node.TEXT_NODE || isBreak(child))) {
        return false;
    }

    if (children.some(isBreak)) {
        node.replaceChildren(...text.split('\n').flatMap((line, index) => (index === 0 ? [line] : [node.ownerDocument.createElement('br'), line])));

        return true;
    }

    if (text.includes('\n') && ! node.textContent.trim().includes('\n')) {
        return false;
    }

    return setText(node, text);
}

function setHref(node, url) {
    if (! node.hasAttribute('href')) {
        return false;
    }

    if (node.getAttribute('href') !== url) {
        node.setAttribute('href', url);
    }

    return true;
}

/*
 * An empty value is never patched: a view usually leaves an empty field out, and the server fills in a default.
 * A choice or a list decides more than one element shows, so it reloads the frame as well. A heading changes its text
 * while the element is its level; another level needs another element, which only the site makes, so it reloads.
 */
function patch(node, type, value) {
    if (value === null || value === '') {
        return false;
    }

    switch (type) {
        case 'text':
            return setText(node, value);
        case 'long_text':
            return setLongText(node, value);
        case 'rich_text':
            if (node.innerHTML !== value) {
                node.innerHTML = value;
            }

            return true;
        case 'link':
            return setHref(node, value);
        case 'button':
            return node.hasAttribute('href') && holdsText(node) && setText(node, value.text) && setHref(node, value.url);
        case 'heading':
            return node.localName === value.tag && setText(node, value.text);
        default:
            return false;
    }
}

function markStale(id, seq) {
    stale.set(id, Math.max(stale.get(id) ?? 0, seq));
}

/* Shows a change on the site; one that cannot be shown reloads the frame after the save that stores it. */
function follow(input) {
    const doc = outlines?.doc;

    // A hidden strip is not on the site, and a frame that is still loading gets the change once it is there.
    if (! doc?.defaultView || strips.get(input.id)?.visible === false) {
        return;
    }

    const nodes = nodesFor(doc, input);
    const shown = nodes.length > 0 && nodes.map((node) => patch(node, input.type, input.value)).every(Boolean);

    if (! shown) {
        markStale(input.id, input.seq);
    }

    readHeadingsLater();
}

/* ---------------------------------------------------------------------------------------------------------------
 * The headings of the page: every h1–h6 in the order of the document, and the element of a heading field that is at
 * "Geen kop" (a span). A span the site prints itself is no heading: the frame cannot know it was meant as one.
 * ------------------------------------------------------------------------------------------------------------ */

let lastHeadings = null;
let headingsTimer = null;

/* The heading fields of a strip ("kop", or "kaarten.*.titel" in a list), with the label of their list. */
function headingField(id, path) {
    const pattern = path.split('.').map((part, index) => (index === 1 ? '*' : part)).join('.');

    return strips.get(id)?.headings?.find((heading) => heading.path === pattern) ?? null;
}

/* What a heading says: its text, else what an image in it or its label says, so a logo in an h1 is no empty heading. */
function headingText(node) {
    const text = node.textContent.replace(/\s+/g, ' ').trim();

    if (text !== '') {
        return text;
    }

    const alt = [...node.querySelectorAll('img[alt]')].map((image) => image.alt.trim()).filter(Boolean).join(' ');

    return alt || node.getAttribute('aria-label')?.trim() || '';
}

/*
 * Whether a visitor sees the heading on the screen. A heading the site hides (display, visibility, opacity, or the
 * one pixel of a text for screen readers only) still counts for search engines, so it stays in the list.
 */
function headingVisible(node) {
    const shown = typeof node.checkVisibility === 'function'
        ? node.checkVisibility({ opacityProperty: true, visibilityProperty: true })
        : node.getClientRects().length > 0;

    if (! shown) {
        return false;
    }

    const box = node.getBoundingClientRect();

    return box.width > 1 || box.height > 1;
}

function headingsOf(doc) {
    const headings = [];

    for (const node of doc.querySelectorAll(LEVELS + ', [' + FIELD + ']')) {
        const level = /^h[1-6]$/.test(node.localName);
        const strip = node.closest('[' + ATTRIBUTE + ']');
        const id = strip?.getAttribute(ATTRIBUTE) ?? null;
        const field = strip !== null && node.hasAttribute(FIELD) ? pathOf(node, strip) : null;
        const known = field === null ? null : headingField(id, field.path);

        if (! level && known === null) {
            continue;
        }

        headings.push({
            tag: level ? node.localName : 'span',
            text: headingText(node),
            strip: id,
            field: field?.path ?? null,
            count: field?.count ?? null,
            heading: known !== null,
            list: known?.list ?? null,
            visible: headingVisible(node),
        });
    }

    return headings;
}

/* Reads the headings a moment later and tells the shell when they changed, or always when it asks (force). */
function readHeadingsLater(force = false) {
    if (config.heading_levels !== true) {
        return;
    }

    window.clearTimeout(headingsTimer);
    headingsTimer = window.setTimeout(() => {
        const doc = outlines?.doc;

        if (! doc?.defaultView) {
            return;
        }

        const headings = headingsOf(doc);
        const key = JSON.stringify(headings);

        if (force || key !== lastHeadings) {
            lastHeadings = key;
            shell.dispatchEvent(new CustomEvent('made-cms:headings', { detail: { headings } }));
        }
    }, READ_AFTER);
}

/* The server stored the changes of a strip up to seq: they are on the site after a reload, which they may need. */
function saved(id, seq) {
    for (const [key, input] of live) {
        if (input.id === id && input.seq <= seq) {
            live.delete(key);
        }
    }

    if (stale.has(id)) {
        if (stale.get(id) <= seq) {
            stale.delete(id);
        }

        reloadFrame();
    }
}

/*
 * Errors come along when the bar chooses a strip that stopped the publication; the panel shows them at the fields.
 * A strip the opbouw just added comes with where it was added (added), so the panel can say so. No strip (null) goes
 * back to the opbouw.
 */
function select(id, errors = null, field = null, added = null) {
    selected = id;
    shell.dispatchEvent(new CustomEvent('made-cms:select', { detail: { id, errors, field: field?.path ?? null, count: field?.count ?? null, added } }));

    for (const button of outlines?.buttons.values() ?? []) {
        button.classList.toggle('actief', button.dataset.strip === id);
        button.setAttribute('aria-pressed', String(button.dataset.strip === id));
    }

    if (id === null) {
        delete shell.dataset.selected;
    } else {
        shell.dataset.selected = id;
    }
}

/*
 * After a save or a publication the frame shows the working copy again, at the place the editor was looking at. After
 * a strip was added or moved that strip may lie elsewhere: once the site shows it, it scrolls into view when it is not.
 */
function reloadFrame(reveal = null) {
    const win = frame?.contentWindow;

    if (! win) {
        return;
    }

    const x = win.scrollX;
    const y = win.scrollY;

    frame.addEventListener('load', () => {
        frame.contentWindow?.scrollTo(x, y);

        if (reveal !== null) {
            show(reveal);
        }
    }, { once: true });
    win.location.reload();
}

/* A site rendered in the browser puts its strips in the page after it loaded, so this looks a few times. */
function show(id, tries = 20) {
    const doc = frame?.contentDocument;
    const element = doc ? stripElement(doc, id) : null;

    if (! element) {
        if (tries > 0) {
            window.setTimeout(() => show(id, tries - 1), 100);
        }

        return;
    }

    const box = element.getBoundingClientRect();
    const height = doc.defaultView.innerHeight;

    if (box.bottom < 0 || box.top > height) {
        element.scrollIntoView({ block: 'start' });
    }
}

function attach() {
    const doc = frame.contentDocument;

    // Only a document of the same origin can be read; a blank frame is the moment before the site arrives.
    if (! doc || doc === outlines?.doc || doc.location.href === 'about:blank') {
        return;
    }

    outlines = new Outlines(doc);

    // What the editor typed while the frame reloaded is not stored yet, so the new document does not show it.
    for (const input of live.values()) {
        follow(input);
    }
}

if (shell) {
    shell.addEventListener('made-cms:input', (event) => {
        live.set(event.detail.id + '\n' + event.detail.path, event.detail);
        follow(event.detail);
    });
    shell.addEventListener('made-cms:saved', (event) => saved(event.detail.id, event.detail.seq ?? Infinity));
    shell.addEventListener('made-cms:published', reloadFrame);
    // The overview of the headings chooses a strip with the field of the heading (field, with count in a list).
    shell.addEventListener('made-cms:choose', (event) => {
        const field = event.detail.field ? { path: event.detail.field, count: event.detail.count ?? null } : null;

        select(event.detail.id ?? null, event.detail.errors ?? null, field, event.detail.added ?? null);
    });
    shell.addEventListener('made-cms:view', (event) => {
        if (event.detail.view === 'headings') {
            readHeadingsLater(true);
        }
    });

    // The opbouw changed: new labels and places, and a frame that shows the strips in their new order.
    shell.addEventListener('made-cms:strips', (event) => {
        useStrips(event.detail.strips);
        outlines?.scan();

        if (event.detail.reload) {
            reloadFrame(event.detail.reveal ?? null);
        }
    });
    shell.addEventListener('made-cms:visibility', (event) => {
        const strip = strips.get(event.detail.id);

        if (strip) {
            strip.visible = event.detail.visible;
        }

        // A strip that appears or goes away changes the whole page: the save reloads it.
        markStale(event.detail.id, 0);
    });
}

if (frame && overlay) {
    frame.addEventListener('load', attach);

    if (frame.contentDocument?.readyState === 'complete') {
        attach();
    }
}
