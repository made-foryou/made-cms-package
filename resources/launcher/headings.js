/*
 * The headings of the page in the side panel of the edit shell (design 11.21–11.22b), next to the opbouw, for whoever
 * chooses the level of a heading.
 *
 * edit.js reads the headings as the frame shows them and hands them over as made-cms:headings: every h1–h6 in the
 * order of the document and the element of a heading field at "Geen kop", each with its strip and field (or null) and
 * whether it is on the screen. This file lists them indented by level, with the signals above the list: no H1, more
 * than one H1, a level skipped and an empty heading. A heading the site hides with css counts too, because search
 * engines read it; a hidden strip is not in the frame, so neither are its headings.
 *
 * A heading of a field opens that field (made-cms:choose with the field, through edit.js and made-cms:select); a
 * heading the site or the view prints is fixed and cannot be clicked. The markup and every text come from the server
 * (cms/edit.blade.php and lang/); this file never holds copy.
 */

const shell = document.querySelector('[data-edit-shell]');
const config = JSON.parse(document.querySelector('script[data-config]')?.textContent ?? '{}');
const texts = config.texts?.headings ?? {};
const levels = texts.levels ?? {};

const slot = (name, root = shell) => root?.querySelector('[data-slot="' + name + '"]');

const ui = {
    section: slot('headings'),
    signals: slot('headings-signals'),
    fine: slot('headings-fine'),
    none: slot('headings-none'),
    list: slot('headings-list'),
};

// The headings edit.js read last; null until it read the frame.
let headings = null;

// The names of the strips of the working copy, by id.
const labels = new Map();

// The heading the editor opened last: "← Koppen" puts the focus back on its row.
let opened = null;

/* Fills :placeholders like Laravel does: the longest name first, so :id never eats into :identifier. */
function format(text, replace = {}) {
    return Object.keys(replace)
        .sort((a, b) => b.length - a.length)
        .reduce((result, key) => result.replaceAll(':' + key, String(replace[key])), text ?? '');
}

function template(part) {
    return document.querySelector('template[data-part="' + part + '"]').content.firstElementChild.cloneNode(true);
}

function useStrips(list) {
    labels.clear();

    for (const strip of list ?? []) {
        labels.set(strip.id, strip.label);
    }
}

/* 1 to 6 for h1 to h6; 0 for a heading at "Geen kop", which is no heading for search engines. */
function levelOf(heading) {
    return /^h[1-6]$/.test(heading.tag) ? Number(heading.tag.charAt(1)) : 0;
}

function badgeOf(heading) {
    return levels[heading.tag] ?? heading.tag;
}

/* A heading of a field of a strip on the page: a click opens that field. */
function clickable(heading) {
    return heading.strip !== null && heading.field !== null && labels.has(heading.strip);
}

/* Where the heading comes from: the strip (with its list), or fixed in the strip or the site. */
function sourceOf(heading) {
    if (! clickable(heading)) {
        return heading.strip === null ? texts.source_site : texts.source_strip;
    }

    const strip = labels.get(heading.strip);

    return heading.list ? format(texts.source_list, { strip, list: heading.list }) : strip;
}

function shownSource(heading) {
    return heading.visible ? sourceOf(heading) : format(texts.not_visible, { source: sourceOf(heading) });
}

function quote(heading) {
    return format(texts.quote, { text: heading.text || texts.empty });
}

/* "A", "A en B", "A, B en C". */
function join(parts) {
    return parts.length < 2 ? (parts[0] ?? '') : parts.slice(0, -1).join(texts.join) + texts.join_last + parts.at(-1);
}

function keyOf(heading) {
    return heading.strip + '\n' + heading.field;
}

/* ---------------------------------------------------------------------------------------------------------------
 * The signals: what search engines read in the structure, on every h1–h6 of the page
 * ------------------------------------------------------------------------------------------------------------ */

function signal(title, meta, target = null, action = null) {
    return { title, meta, target, action: target === null ? null : (action ?? format(texts.open, { source: sourceOf(target) })) };
}

function signalsOf(list) {
    const ranked = list.filter((heading) => levelOf(heading) > 0);
    const ones = ranked.filter((heading) => levelOf(heading) === 1);
    const signals = [];
    const flagged = new Set();

    if (ones.length === 0) {
        const first = list.find((heading) => heading.heading && clickable(heading)) ?? null;

        signals.push(signal(texts.no_h1, texts.no_h1_meta, first, texts.no_h1_action));
    }

    if (ones.length > 1) {
        const target = ones.slice(1).find((heading) => heading.heading && clickable(heading))
            ?? ones.find((heading) => heading.heading && clickable(heading))
            ?? null;

        ones.forEach((heading) => flagged.add(heading));
        signals.push(signal(
            format(texts.many_h1, { count: ones.length }),
            format(texts.many_h1_meta, { texts: join(ones.map(quote)) }),
            target,
        ));
    }

    for (let index = 1; index < ranked.length; index++) {
        const before = levelOf(ranked[index - 1]);
        const heading = ranked[index];
        const after = levelOf(heading);

        if (after > before + 1) {
            const missing = levels['h' + (before + 1)];
            const chooses = heading.heading && clickable(heading);

            flagged.add(heading);
            signals.push(signal(
                format(texts.skipped, { before: levels['h' + before], after: levels['h' + after], missing }),
                format(chooses ? texts.skipped_meta : texts.skipped_fixed_meta, { text: quote(heading), missing }),
                chooses ? heading : null,
            ));
        }
    }

    for (const heading of ranked.filter((candidate) => candidate.text === '')) {
        const field = clickable(heading);

        flagged.add(heading);
        signals.push(signal(
            texts.empty_heading,
            format(field ? texts.empty_meta : texts.empty_fixed_meta, { level: badgeOf(heading), source: sourceOf(heading) }),
            field ? heading : null,
        ));
    }

    return { signals, flagged };
}

/* ---------------------------------------------------------------------------------------------------------------
 * Drawing
 * ------------------------------------------------------------------------------------------------------------ */

function open(heading) {
    opened = keyOf(heading);
    shell.dispatchEvent(new CustomEvent('made-cms:choose', { detail: { id: heading.strip, field: heading.field, count: heading.count } }));
}

function signalElement(entry) {
    const element = template('heading-signal');
    const action = slot('headings-signal-action', element);

    slot('headings-signal-title', element).textContent = entry.title;
    slot('headings-signal-meta', element).textContent = entry.meta;
    action.hidden = entry.action === null;

    if (entry.action !== null) {
        action.textContent = entry.action;
        action.addEventListener('click', () => open(entry.target));
    }

    return element;
}

function rowElement(heading, flagged) {
    const link = clickable(heading);
    const item = template(link ? 'heading-row' : 'heading-fixed');
    const row = item.firstElementChild;
    const text = slot('headings-text', item);
    const level = levelOf(heading);

    item.dataset.level = String(level);
    row.classList.toggle('h1', level === 1);
    row.classList.toggle('signaal', flagged.has(heading));
    row.classList.toggle('css', ! heading.visible);
    slot('headings-level', item).textContent = badgeOf(heading);
    text.textContent = heading.text || texts.empty;
    text.classList.toggle('leeg', heading.text === '');
    slot('headings-source', item).textContent = shownSource(heading);

    if (link) {
        item.dataset.heading = keyOf(heading);
        row.setAttribute('aria-label', format(texts.row, { level: badgeOf(heading), text: heading.text || texts.row_empty, source: shownSource(heading) }));
        row.addEventListener('click', () => open(heading));
    }

    return item;
}

/* The row of a heading the editor opened; the first of them when a strip shows the field more than once. */
function focusRow(key) {
    [...ui.list.querySelectorAll('[data-heading]')].find((item) => item.dataset.heading === key)?.querySelector('button')?.focus();
}

function render() {
    if (headings === null) {
        return;
    }

    // A new list replaces the rows; the focus stays on the heading it was on.
    const focused = ui.list.contains(document.activeElement) ? document.activeElement.closest('[data-heading]')?.dataset.heading : null;
    const { signals, flagged } = signalsOf(headings);

    ui.signals.replaceChildren(...signals.map(signalElement));
    ui.fine.hidden = signals.length > 0;
    ui.none.hidden = headings.length > 0;
    ui.list.hidden = headings.length === 0;
    ui.list.replaceChildren(...headings.map((heading) => rowElement(heading, flagged)));

    if (focused) {
        focusRow(focused);
    }
}

if (shell && ui.section && ui.list) {
    useStrips(config.strips);

    shell.addEventListener('made-cms:headings', (event) => {
        headings = event.detail.headings ?? [];
        render();
    });

    shell.addEventListener('made-cms:strips', (event) => {
        useStrips(event.detail.strips);
        render();
    });

    // "← Koppen": the opbouw script shows the headings again, the focus goes back to the heading that was opened.
    shell.addEventListener('made-cms:select', (event) => {
        if ((event.detail.id ?? null) === null && ! ui.section.hidden && opened !== null) {
            focusRow(opened);
        }
    });
}
