/*
 * Made CMS edit mode (design "Made CMS — Inloggen", 1.7): while the edit bar shows, every strip of the host page gets an
 * outline with the label of its type. The page only marks a strip with its id (data-made-cms-strip); which type it is and
 * how that type is called always comes from the server. The outlines live in the launcher's shadow root, so the host's
 * css never reaches them. Loaded on demand by launcher.js, only for logged-in editors; all copy comes from its templates.
 * Timings follow the motion spec (1.9): outlines come in staggered, 240 ms each, the first 120 ms after the bar.
 */

const MARKER = 'data-made-cms-strip';
const BATCH = 100;
const STAGGER_START = 120;
const STAGGER_STEP = 80;
const SCAN_DELAY = 50;

export class EditMode {
    /**
     * @param {ShadowRoot} root The launcher's shadow root, holding the outline layer and its template.
     * @param {object} options
     * @param {(ids: number[]) => Promise<Array<{id: number, type: string, label: string}>>} options.describe Asks the server about strip ids.
     * @param {(state: {selected: ({id: number, type: string, label: string}|null), count: number}) => void} options.onChange Hears every change of the outlines or the selection.
     */
    constructor(root, { describe, onChange }) {
        this.layer = root.querySelector('.omlijningen');
        this.template = root.querySelector('template[data-outline]');
        this.describe = describe;
        this.onChange = onChange;
        this.known = new Map();
        this.asked = new Set();
        this.outlines = new Map();
        this.selected = null;
        this.running = false;
        this.animate = false;
        this.frame = null;
        this.scanTimer = null;
    }

    /* Starts outlining. With animate, the first outlines come in one after the other while the bar slides in. */
    start({ animate = false } = {}) {
        if (this.running) {
            return;
        }

        this.running = true;
        this.animate = animate;
        this.layer.hidden = false;

        // Inertia, Livewire and Turbo swap parts of the page without a reload: new strips get an outline, gone ones lose it.
        this.mutations = new MutationObserver(() => this.queueScan());
        this.mutations.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: [MARKER] });

        // Outlines sit in page coordinates, so scrolling needs nothing; only a change of layout moves them.
        this.resizes = new ResizeObserver(() => this.schedulePlace());
        this.resizes.observe(document.body);
        this.onLayout = () => this.schedulePlace();
        window.addEventListener('resize', this.onLayout);
        window.addEventListener('load', this.onLayout);
        document.fonts?.ready.then(this.onLayout);

        this.onKey = (event) => {
            if (event.key === 'Escape' && this.selected) {
                this.select(null);
            }
        };
        document.addEventListener('keydown', this.onKey);

        this.scan();
    }

    stop() {
        if (!this.running) {
            return;
        }

        this.running = false;
        this.mutations.disconnect();
        this.resizes.disconnect();
        window.removeEventListener('resize', this.onLayout);
        window.removeEventListener('load', this.onLayout);
        document.removeEventListener('keydown', this.onKey);
        clearTimeout(this.scanTimer);

        if (this.frame !== null) {
            cancelAnimationFrame(this.frame);
            this.frame = null;
        }

        this.outlines.forEach(({ button }) => button.remove());
        this.outlines.clear();
        this.selected = null;
        this.layer.hidden = true;
        this.onChange({ selected: null, count: 0 });
    }

    queueScan() {
        clearTimeout(this.scanTimer);
        this.scanTimer = setTimeout(() => this.scan(), SCAN_DELAY);
    }

    /* Finds the marked strips, asks the server once about ids it does not know yet, then draws the outlines. */
    async scan() {
        const unknown = [...this.marked().keys()].filter((id) => !this.known.has(id) && !this.asked.has(id));

        for (let start = 0; start < unknown.length; start += BATCH) {
            const ids = unknown.slice(start, start + BATCH);

            ids.forEach((id) => this.asked.add(id));

            try {
                (await this.describe(ids)).forEach((strip) => this.known.set(strip.id, strip));
            } catch {
                // No answer: these strips get no outline for now and are asked about again after the next change of the page.
                ids.forEach((id) => this.asked.delete(id));
            }
        }

        if (this.running) {
            this.draw(this.marked());
        }
    }

    /* The marked elements of the page by strip id; when an id appears twice, the first element wins. */
    marked() {
        const elements = new Map();

        document.querySelectorAll(`[${MARKER}]`).forEach((element) => {
            const id = Number(element.getAttribute(MARKER));

            if (Number.isInteger(id) && id > 0 && !elements.has(id)) {
                elements.set(id, element);
            }
        });

        return elements;
    }

    /* Adds an outline for every known strip on the page and removes the outlines of strips that left it. */
    draw(elements) {
        let index = 0;

        elements.forEach((element, id) => {
            const strip = this.known.get(id);
            const outline = this.outlines.get(id);

            if (!strip) {
                return;
            }

            if (outline) {
                if (outline.element !== element) {
                    this.resizes.unobserve(outline.element);
                    this.resizes.observe(element);
                    outline.element = element;
                }

                return;
            }

            const button = this.template.content.firstElementChild.cloneNode(true);

            button.querySelector('[data-label]').textContent = strip.label;
            button.addEventListener('click', () => this.select(this.selected?.id === id ? null : strip));

            if (this.animate) {
                button.classList.add('in');
                button.style.animationDelay = `${STAGGER_START + index * STAGGER_STEP}ms`;
                index += 1;
            }

            this.layer.append(button);
            this.resizes.observe(element);
            this.outlines.set(id, { element, button });
        });

        this.outlines.forEach(({ element, button }, id) => {
            if (!elements.has(id)) {
                this.resizes.unobserve(element);
                button.remove();
                this.outlines.delete(id);
            }
        });

        // Only the outlines that come with the bar stagger in; strips that appear later show at once.
        this.animate = false;

        if (this.selected && !this.outlines.has(this.selected.id)) {
            this.selected = null;
        }

        this.place();
        this.onChange({ selected: this.selected, count: this.outlines.size });
    }

    schedulePlace() {
        if (this.frame !== null || !this.running) {
            return;
        }

        this.frame = requestAnimationFrame(() => {
            this.frame = null;
            this.place();
        });
    }

    /*
     * Puts every outline over its strip. Measured against the layer itself, so it works whatever element the layer is
     * positioned in. All reads come before all writes, so the browser lays the page out once.
     */
    place() {
        const origin = this.layer.getBoundingClientRect();
        const boxes = [...this.outlines.values()].map(({ element, button }) => ({ button, rect: element.getBoundingClientRect() }));

        boxes.forEach(({ button, rect }) => {
            button.hidden = rect.width === 0 && rect.height === 0;
            button.style.left = `${rect.left - origin.left}px`;
            button.style.top = `${rect.top - origin.top}px`;
            button.style.width = `${rect.width}px`;
            button.style.height = `${rect.height}px`;
        });
    }

    /* Selects a strip, or nothing: its outline stays lit and the bar names it. The content side panel will open from here. */
    select(strip) {
        this.selected = strip;
        this.outlines.forEach(({ button }, id) => button.setAttribute('aria-pressed', String(strip?.id === id)));
        this.onChange({ selected: strip, count: this.outlines.size });
    }
}
