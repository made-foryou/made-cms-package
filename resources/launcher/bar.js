/*
 * The bar of the edit shell: whether the page is live, how many strips the draft changes, "Publiceren", "Concept
 * weggooien" and the two ways out of the shell: "‹ Pagina's" and "Bewerken stoppen". "Voorvertoning" is a plain link to
 * the page in a tab of its own.
 *
 * The markup and every text come from the server (cms/edit.blade.php and lang/). The state of the draft and its texts
 * come from GET …/draft, so this file never counts strips or builds a plural. It talks JSON, so the shell and the site
 * in its frame stay loaded. It waits for the side panel before it publishes (made-cms:flush) or discards
 * (made-cms:discarding), reloads the frame after publishing (made-cms:published) and chooses a strip that stopped the
 * publication (made-cms:choose). After discarding the whole shell reloads: strips may have changed or gone. A strip
 * added, moved, removed or put back (made-cms:strips from structure.js) changes the draft as well.
 *
 * Leaving the shell: both ways out are plain links that also work without this file. With it they first wait until
 * the side panel has stored what it holds (a photo on its way counts), and nothing is lost: when that fails the editor
 * stays, with the strip that failed chosen and the reason on the line under the bar. "Bewerken stoppen" asks what to do
 * with a draft first, in a choice under the button; the editor leaves with "Stoppen zonder publiceren" (the draft is
 * kept) or "Publiceren". The addresses come from the server (`exits` in every state of the draft), which knows what visitors see.
 */

const shell = document.querySelector('[data-edit-shell]');
const config = JSON.parse(document.querySelector('script[data-config]')?.textContent ?? '{}');
const texts = config.texts?.bar ?? {};
const labels = new Map((config.strips ?? []).map((strip) => [strip.id, strip.label]));

const slot = (name) => shell?.querySelector('[data-slot="' + name + '"]');

const ui = {
    bar: shell?.querySelector('.bewerkbalk'),
    page: slot('bar-page'),
    state: slot('bar-state'),
    stay: slot('bar-stay'),
    discard: slot('bar-discard'),
    discardText: slot('bar-discard-text'),
    discardConfirm: slot('bar-discard-confirm'),
    publish: slot('bar-publish'),
    pages: slot('bar-pages'),
    pagesText: slot('bar-pages-text'),
    pagesWait: slot('bar-pages-wait'),
    stop: slot('bar-stop'),
    stopText: slot('bar-stop-text'),
    stopWait: slot('bar-stop-wait'),
    choice: slot('bar-stop-choice'),
    choiceTitle: slot('bar-stop-title'),
    choiceText: slot('bar-stop-text-choice'),
    choiceLeave: slot('bar-stop-leave'),
    choicePublish: slot('bar-stop-publish'),
    notice: slot('bar-notice'),
    progress: slot('bar-progress'),
};

let draft = config.draft ?? null;
// Where leaving goes: it changes with the page (published, it is on the site), so every state of the draft brings it.
let exits = draft?.exits ?? config.exits;
let asking = 0;
let busy = false;
let leaving = null;
let navigating = false;

/* Fills :placeholders like Laravel does: the longest name first, so :id never eats into :identifier. */
function format(text, replace = {}) {
    return Object.keys(replace)
        .sort((a, b) => b.length - a.length)
        .reduce((result, key) => result.replaceAll(':' + key, String(replace[key])), text ?? '');
}

async function request(method, url) {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': config.csrf_token ?? '',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    let data = null;

    try {
        data = await response.json();
    } catch {
        data = null;
    }

    return { status: response.status, data };
}

function failure() {
    return { status: 0, data: null };
}

/* ---------------------------------------------------------------------------------------------------------------
 * The state of the draft
 * ------------------------------------------------------------------------------------------------------------ */

/*
 * The state has a long text and a short one: on a narrow bar (below 1100 px, as in cms.css) the short one is written,
 * "2 wijzigingen" for "Concept · 2 wijzigingen". The text is one text node, so a screen reader hears only what shows.
 */
const narrow = window.matchMedia('(max-width: 1100px)');
let said = null;

function write(long, short = long) {
    said = { long, short };
    ui.state.textContent = (narrow.matches ? short : long) ?? '';
}

function show(state) {
    draft = state;
    ui.page.textContent = state.live ? texts.live : texts.draft;
    ui.page.classList.toggle('live', state.live);

    // While publishing, discarding or leaving the bar keeps saying so; a save on the way asks for the state meanwhile.
    if (! busy) {
        write(state.label, state.short_label);
        delete ui.state.dataset.state;
    }

    ui.discardText.textContent = state.discard_text;
    ui.discard.hidden = ! state.discardable;
    ui.discard.open = ui.discard.open && state.discardable;
    ui.choiceTitle.textContent = state.stop_title;
    ui.choiceText.textContent = state.stop_text;

    if (state.exits) {
        exits = state.exits;
        ui.stop.setAttribute('href', exits.stop);
    }
    ui.publish.disabled = busy || ! state.publishable;
}

/* A message in place of the state, until the next change of the draft brings the state back. */
function say(text, state = 'error', short = text) {
    write(text, short);
    ui.state.dataset.state = state;
}

/* The full text of a refusal on a line of its own under the bar; the bar itself never changes in height otherwise. */
function notify(text) {
    ui.notice.textContent = text;
    ui.notice.hidden = false;
    ui.bar.classList.add('met-melding');
}

function clearNotice() {
    ui.notice.hidden = true;
    ui.notice.textContent = '';
    ui.bar.classList.remove('met-melding');
}

/* Only the answer to the last question counts: a save during publishing asks again. */
async function refresh() {
    const ticket = ++asking;
    const { status, data } = await request('GET', config.urls.draft).catch(failure);

    if (ticket === asking && status === 200 && data?.draft) {
        show(data.draft);
    }
}

/* ---------------------------------------------------------------------------------------------------------------
 * Waiting for the side panel: it hands a promise to wait() that resolves to false when it could not save. report()
 * hears what the panel tells on the way (a photo still uploading, the strip that failed).
 * ------------------------------------------------------------------------------------------------------------ */

async function ask(type, detail = {}, report = null) {
    const waits = [];

    shell.dispatchEvent(new CustomEvent(type, {
        detail: { ...detail, report: (info) => report?.(info), wait: (promise) => waits.push(promise) },
    }));

    return (await Promise.all(waits)).every((result) => result !== false);
}

/* ---------------------------------------------------------------------------------------------------------------
 * A refusal of what the panel held, named with the strip that failed; the panel shows the errors at the fields.
 * ------------------------------------------------------------------------------------------------------------ */

function notSaved(failed) {
    say(texts.not_saved_state);

    const named = (message) => notify(format(texts.not_saved, { strip: labels.get(failed.id) ?? texts.unknown, message }));

    if (failed?.kind === 'invalid') {
        named(Object.values(failed.errors ?? {})[0]?.[0] ?? '');
        shell.dispatchEvent(new CustomEvent('made-cms:choose', { detail: { id: failed.id, errors: failed.errors } }));

        return;
    }

    // A photo that did not upload, or an alt text the server refused, stays in its field with its error.
    if (failed?.kind === 'upload' || failed?.kind === 'alt') {
        named(failed.kind === 'upload' ? texts.upload_failed : texts.alt_failed);
        shell.dispatchEvent(new CustomEvent('made-cms:choose', { detail: { id: failed.id } }));

        return;
    }

    notify(failed?.kind === 'expired' ? texts.expired : texts.failed);
}

/* ---------------------------------------------------------------------------------------------------------------
 * Publishing
 * ------------------------------------------------------------------------------------------------------------ */

/*
 * The server keys the errors of a strip as "strips.<id>.<field path>". The bar names the first strip with its first
 * message and chooses that strip, so the panel shows the errors at its fields.
 */
function refused(errors) {
    if (errors.page) {
        say(texts.not_published_state);
        notify(errors.page[0]);
        refresh();

        return;
    }

    const strips = new Map();

    for (const [key, messages] of Object.entries(errors)) {
        const match = /^strips\.([^.]+)\.(.+)$/.exec(key);

        if (match) {
            strips.set(match[1], { ...strips.get(match[1]), [match[2]]: messages });
        }
    }

    const [id, fields] = strips.entries().next().value ?? [];

    if (id === undefined) {
        say(texts.not_published_state);
        notify(texts.failed);

        return;
    }

    say(texts.not_published_state);
    notify(format(texts.publish_invalid, {
        strip: labels.get(id) ?? texts.unknown,
        message: Object.values(fields)[0]?.[0] ?? '',
    }));
    shell.dispatchEvent(new CustomEvent('made-cms:choose', { detail: { id, errors: fields } }));
}

/* With leave the editor goes to the site (or Pagina's) once the page is published, instead of staying in the shell. */
async function publish({ leave = false } = {}) {
    if (busy) {
        return;
    }

    busy = true;
    clearNotice();
    ui.publish.disabled = true;
    say(texts.publishing, 'busy');

    try {
        let failed = null;

        if (! await ask('made-cms:flush', {}, (info) => { failed = info.failed ?? failed; })) {
            notSaved(failed);

            return;
        }

        const { status, data } = await request('POST', config.urls.publish).catch(failure);

        if (status === 204 && leave) {
            // The page is on its way: the bar keeps saying "Bezig met publiceren…" and stays off until it is gone.
            navigate(exits.publish);

            return;
        }

        if (status === 204) {
            shell.dispatchEvent(new CustomEvent('made-cms:published'));
            await refresh();
            say(texts.published, 'done');
        } else if (status === 422) {
            refused(data?.errors ?? {});
        } else {
            say(texts.not_published_state);
            notify(status === 401 || status === 419 ? texts.expired : texts.failed);
        }
    } finally {
        if (! navigating) {
            busy = false;
            ui.publish.disabled = ! draft?.publishable;
        }
    }
}

/* ---------------------------------------------------------------------------------------------------------------
 * Discarding: the panel stops saving and waits for a save on its way, so no late save brings a draft back.
 * ------------------------------------------------------------------------------------------------------------ */

async function discard() {
    if (busy) {
        return;
    }

    busy = true;
    clearNotice();
    ui.publish.disabled = true;
    ui.discardConfirm.disabled = true;
    say(texts.discarding, 'busy');

    let settle;
    const done = new Promise((resolve) => {
        settle = resolve;
    });

    await ask('made-cms:discarding', { done });

    const { status } = await request('DELETE', config.urls.draft).catch(failure);

    settle(status === 204);

    if (status === 204) {
        window.location.reload();

        return;
    }

    busy = false;
    ui.publish.disabled = ! draft?.publishable;
    ui.discardConfirm.disabled = false;
    say(status === 401 || status === 419 ? texts.expired : texts.failed);
}

/* ---------------------------------------------------------------------------------------------------------------
 * Leaving the shell
 * ------------------------------------------------------------------------------------------------------------ */

/* The geklikte link keeps its width and says what it waits for; the rest of the bar goes dim and a line runs below. */
function waiting(origin, uploading) {
    const link = origin === 'stop' ? ui.stop : ui.pages;
    const text = origin === 'stop' ? ui.stopText : ui.pagesText;
    const mark = origin === 'stop' ? ui.stopWait : ui.pagesWait;

    if (! link.style.minWidth) {
        link.style.minWidth = link.getBoundingClientRect().width + 'px';
    }

    ui.bar.dataset.waiting = origin;
    ui.progress.hidden = false;
    mark.hidden = false;
    link.setAttribute('aria-busy', 'true');
    link.setAttribute('aria-label', origin === 'stop' ? texts.stop_waiting : texts.pages_waiting);
    text.textContent = uploading ? texts.uploading : (origin === 'stop' ? texts.saving : texts.waiting);
    ui.stay.hidden = ! uploading;
    say(uploading ? texts.uploading_state : texts.waiting, 'busy', uploading ? '' : texts.waiting);
}

function resting() {
    for (const [link, text, mark, label] of [
        [ui.stop, ui.stopText, ui.stopWait, ui.stop.dataset.label],
        [ui.pages, ui.pagesText, ui.pagesWait, ui.pages.dataset.label],
    ]) {
        link.style.minWidth = '';
        link.removeAttribute('aria-busy');
        link.setAttribute('aria-label', label);
        text.textContent = text.dataset.rest;
        mark.hidden = true;
    }

    delete ui.bar.dataset.waiting;
    ui.progress.hidden = true;
    ui.stay.hidden = true;
    busy = false;
    ui.publish.disabled = ! draft?.publishable;

    if (draft) {
        write(draft.label, draft.short_label);
        delete ui.state.dataset.state;
    }
}

/*
 * Waits until the panel has stored everything it holds. True when the editor can go; false when they stay: a save that
 * failed (named under the bar) or "Blijven" while a photo was still uploading.
 */
async function settled(origin) {
    if (busy) {
        return false;
    }

    busy = true;
    clearNotice();
    ui.publish.disabled = true;

    const attempt = { cancelled: false };
    let failed = null;

    leaving = attempt;
    waiting(origin, false);

    let stored = false;

    try {
        stored = await ask('made-cms:flush', {}, (info) => {
            if (info.uploading && ! attempt.cancelled) {
                waiting(origin, true);
            }

            failed = info.failed ?? failed;
        });
    } catch {
        // The panel could not even try: the editor stays, and nothing waits for ever.
        failed = { kind: 'failed' };
    }

    if (attempt.cancelled) {
        return false;
    }

    if (! stored) {
        resting();
        notSaved(failed);

        return false;
    }

    return true;
}

/*
 * Goes to another page. A navigation that does not happen (the browser's Stop, a download, a refused prompt) must not
 * leave the bar waiting for ever: after a while it rests again and asks the state of the draft.
 */
const NAVIGATION_GRACE = 10000;

function navigate(url) {
    navigating = true;
    window.location.assign(url);
    window.setTimeout(() => {
        navigating = false;
        resting();
        refresh();
    }, NAVIGATION_GRACE);
}

async function leave(url, origin) {
    if (await settled(origin)) {
        navigate(url);
    }
}

/* A photo that is still uploading lands in the library either way; the editor only goes on editing. */
function stay() {
    if (leaving) {
        leaving.cancelled = true;
    }

    resting();
    ui.stop.focus();
}

async function stop() {
    if (! await settled('stop')) {
        return;
    }

    // Counted again now that the panel stored its changes: only a draft that changes something asks what to do.
    await refresh();

    if (! draft?.discardable) {
        navigate(exits.stop);

        return;
    }

    resting();
    openChoice();
}

function openChoice() {
    ui.choice.hidden = false;
    ui.stop.setAttribute('aria-expanded', 'true');
    ui.choiceLeave.focus();
}

function closeChoice(refocus = false) {
    if (ui.choice.hidden) {
        return;
    }

    ui.choice.hidden = true;
    ui.stop.setAttribute('aria-expanded', 'false');

    if (refocus) {
        ui.stop.focus();
    }
}

/* A click with a modifier or on another button is the browser's: a new tab, a download, a context menu. */
function plain(event) {
    return event.button === 0 && ! (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey);
}

/* ---------------------------------------------------------------------------------------------------------------
 * Wiring
 * ------------------------------------------------------------------------------------------------------------ */

if (shell && ui.publish && ui.state) {
    // The texts of the links at rest, to put back after waiting.
    for (const [link, text] of [[ui.stop, ui.stopText], [ui.pages, ui.pagesText]]) {
        link.dataset.label = link.getAttribute('aria-label') ?? text.textContent.trim();
        text.dataset.rest = text.textContent;
    }

    narrow.addEventListener('change', () => said && write(said.long, said.short));
    write(draft?.label ?? ui.state.textContent, draft?.short_label);

    ui.stop.setAttribute('aria-haspopup', 'dialog');
    ui.stop.setAttribute('aria-expanded', 'false');

    ui.publish.addEventListener('click', () => {
        publish();
    });
    ui.discardConfirm?.addEventListener('click', discard);
    ui.pages.addEventListener('click', (event) => {
        if (plain(event)) {
            event.preventDefault();
            leave(ui.pages.getAttribute('href'), 'pages');
        }
    });
    ui.stop.addEventListener('click', (event) => {
        if (plain(event)) {
            event.preventDefault();
            closeChoice();
            stop();
        }
    });
    ui.stay.addEventListener('click', stay);
    ui.choiceLeave.addEventListener('click', () => {
        closeChoice();
        leave(exits.stop, 'stop');
    });
    ui.choicePublish.addEventListener('click', () => {
        closeChoice();
        publish({ leave: true });
    });
    // Back to the shell from the bfcache: the page shows the waiting it left in, and nothing is waiting any more.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            navigating = false;
            resting();
        }
    });
    shell.addEventListener('made-cms:saved', () => {
        clearNotice();
        refresh();
    });
    shell.addEventListener('made-cms:strips', (event) => {
        for (const strip of event.detail.strips ?? []) {
            labels.set(strip.id, strip.label);
        }

        refresh();
    });

    // The confirmation and the choice close with Escape and when the editor clicks elsewhere, like a menu.
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        if (! ui.choice.hidden) {
            closeChoice(true);
        } else if (ui.discard?.open) {
            ui.discard.open = false;
            ui.discard.querySelector('summary')?.focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (ui.discard?.open && ! ui.discard.contains(event.target)) {
            ui.discard.open = false;
        }

        if (! ui.choice.hidden && ! ui.choice.contains(event.target) && ! ui.stop.contains(event.target)) {
            closeChoice();
        }
    });
}
