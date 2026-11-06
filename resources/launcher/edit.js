/*
 * The edit shell: the page of the site in a frame of the same origin, with an outline over every strip.
 *
 * The markup, the outline and every text come from the server (cms/edit.blade.php and lang/);
 * this file only adds behaviour. The outlines live in a shadow root of one element of its own, so the css of
 * the site and of the outlines never touch each other. The site itself only changes where it shows a field with
 * data-cms-field: while the editor types, the new value is patched into that element (made-cms:input from panel.js).
 * A change it cannot show that way reloads the frame after the next save.
 */

const HOST = 'made-cms-outlines';
const ATTRIBUTE = 'data-cms-strip';
const FIELD = 'data-cms-field';
const ITEMS = 'data-cms-items';

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
    hidden: shell?.querySelector('[data-slot="hidden-strips"]'),
    hiddenList: shell?.querySelector('[data-slot="hidden-list"]'),
};

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
                if (strip.visible && ! this.warned.has(strip.id) && this.positionOf(strip.id) === null) {
                    this.warned.add(strip.id);
                    console.warn(format(texts.missing, { id: strip.id, type: strip.type }));
                }
            }
        }, WARN_AFTER);
    }
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
 * A choice or a list decides more than one element shows, so it reloads the frame as well.
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

/* Errors come along when the bar chooses a strip that stopped the publication; the panel shows them at the fields. */
function select(id, errors = null, field = null) {
    selected = id;
    shell.dispatchEvent(new CustomEvent('made-cms:select', { detail: { id, errors, field: field?.path ?? null, count: field?.count ?? null } }));

    for (const button of outlines?.buttons.values() ?? []) {
        button.classList.toggle('actief', button.dataset.strip === id);
        button.setAttribute('aria-pressed', String(button.dataset.strip === id));
    }

    showPanel();
}

/*
 * The panel names the chosen strip and where it is on the page. While the frame reloads the strip is briefly
 * not there; the panel then keeps what it showed, and the outline is chosen again once the strip is back.
 * A hidden strip is not on the site in the edit mode, so the panel says it is hidden instead.
 */
function showPanel() {
    if (selected === null) {
        return;
    }

    const position = outlines?.positionOf(selected);
    const hidden = strips.get(selected)?.visible === false;

    if (! position && ! hidden) {
        return;
    }

    panel.choose.hidden = true;
    panel.strip.hidden = false;
    panel.name.textContent = labelOf(selected);
    panel.position.textContent = position ? format(texts.position, position) : texts.hidden;
    shell.dataset.selected = selected;
    showHidden();
}

/* Hidden strips are not in the frame, so the panel lists them: choosing one lets the editor show it again. */
function showHidden() {
    const hidden = [...strips.values()].filter((strip) => strip.visible === false && strip.id !== selected);

    panel.hiddenList.replaceChildren(...hidden.map((strip) => {
        const item = document.createElement('li');
        const button = document.createElement('button');

        button.type = 'button';
        button.className = 'actie klein';
        button.textContent = strip.label;
        button.addEventListener('click', () => select(strip.id));
        item.append(button);

        return item;
    }));
    panel.hidden.hidden = hidden.length === 0;
}

/* After a save or a publication the frame shows the working copy again, at the place the editor was looking at. */
function reloadFrame() {
    const win = frame?.contentWindow;

    if (! win) {
        return;
    }

    const x = win.scrollX;
    const y = win.scrollY;

    frame.addEventListener('load', () => frame.contentWindow?.scrollTo(x, y), { once: true });
    win.location.reload();
}

function attach() {
    const doc = frame.contentDocument;

    // Only a document of the same origin can be read; a blank frame is the moment before the site arrives.
    if (! doc || doc === outlines?.doc || doc.location.href === 'about:blank') {
        return;
    }

    outlines = new Outlines(doc);

    // The first scan ran inside the constructor, while outlines still pointed at the document before the reload.
    showPanel();

    // What the editor typed while the frame reloaded is not stored yet, so the new document does not show it.
    for (const input of live.values()) {
        follow(input);
    }
}

if (shell && panel.hidden) {
    shell.addEventListener('made-cms:input', (event) => {
        live.set(event.detail.id + '\n' + event.detail.path, event.detail);
        follow(event.detail);
    });
    shell.addEventListener('made-cms:saved', (event) => saved(event.detail.id, event.detail.seq ?? Infinity));
    shell.addEventListener('made-cms:published', reloadFrame);
    shell.addEventListener('made-cms:choose', (event) => select(event.detail.id, event.detail.errors ?? null));
    shell.addEventListener('made-cms:visibility', (event) => {
        const strip = strips.get(event.detail.id);

        if (strip) {
            strip.visible = event.detail.visible;
        }

        // A strip that appears or goes away changes the whole page: the save reloads it.
        markStale(event.detail.id, 0);
        showPanel();
        showHidden();
    });
    showHidden();
}

if (frame && overlay) {
    frame.addEventListener('load', attach);

    if (frame.contentDocument?.readyState === 'complete') {
        attach();
    }
}
