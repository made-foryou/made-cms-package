/*
 * The bar of the edit shell: whether the page is live, how many strips the draft changes, "Publiceren" and
 * "Concept weggooien". "Voorvertoning" is a plain link to the page in a tab of its own.
 *
 * The markup and every text come from the server (cms/edit.blade.php and lang/). The state of the draft and its texts
 * come from GET …/draft, so this file never counts strips or builds a plural. It talks JSON, so the shell and the site
 * in its frame stay loaded. It waits for the side panel before it publishes (made-cms:flush) or discards
 * (made-cms:discarding), reloads the frame after publishing (made-cms:published) and chooses a strip that stopped the
 * publication (made-cms:choose). After discarding the whole shell reloads: strips may have changed or gone.
 */

const shell = document.querySelector('[data-edit-shell]');
const config = JSON.parse(document.querySelector('script[data-config]')?.textContent ?? '{}');
const texts = config.texts?.bar ?? {};
const labels = new Map((config.strips ?? []).map((strip) => [strip.id, strip.label]));

const slot = (name) => shell?.querySelector('[data-slot="' + name + '"]');

const ui = {
    page: slot('bar-page'),
    state: slot('bar-state'),
    discard: slot('bar-discard'),
    discardText: slot('bar-discard-text'),
    discardConfirm: slot('bar-discard-confirm'),
    publish: slot('bar-publish'),
};

let draft = config.draft ?? null;
let asking = 0;
let busy = false;

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

function show(state) {
    draft = state;
    ui.page.textContent = state.live ? texts.live : texts.draft;
    ui.page.classList.toggle('live', state.live);

    // While publishing or discarding the bar keeps saying so; a save on the way asks for the state meanwhile.
    if (! busy) {
        ui.state.textContent = state.label;
        delete ui.state.dataset.state;
    }

    ui.discardText.textContent = state.discard_text;
    ui.discard.hidden = ! state.discardable;
    ui.discard.open = ui.discard.open && state.discardable;
    ui.publish.disabled = busy || ! state.publishable;
}

/* A message in place of the state, until the next change of the draft brings the state back. */
function say(text, state = 'error') {
    ui.state.textContent = text ?? '';
    ui.state.dataset.state = state;
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
 * Waiting for the side panel: it hands a promise to wait() that resolves to false when it could not save.
 * ------------------------------------------------------------------------------------------------------------ */

async function ask(type, detail = {}) {
    const waits = [];

    shell.dispatchEvent(new CustomEvent(type, { detail: { ...detail, wait: (promise) => waits.push(promise) } }));

    return (await Promise.all(waits)).every((result) => result !== false);
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
        say(errors.page[0]);
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
        say(texts.failed);

        return;
    }

    say(format(texts.publish_invalid, {
        strip: labels.get(id) ?? texts.unknown,
        message: Object.values(fields)[0]?.[0] ?? '',
    }));
    shell.dispatchEvent(new CustomEvent('made-cms:choose', { detail: { id, errors: fields } }));
}

async function publish() {
    if (busy) {
        return;
    }

    busy = true;
    ui.publish.disabled = true;
    say(texts.publishing, 'busy');

    try {
        if (! await ask('made-cms:flush')) {
            say(texts.publish_unsaved);

            return;
        }

        const { status, data } = await request('POST', config.urls.publish).catch(failure);

        if (status === 204) {
            shell.dispatchEvent(new CustomEvent('made-cms:published'));
            await refresh();
            say(texts.published, 'done');
        } else if (status === 422) {
            refused(data?.errors ?? {});
        } else {
            say(status === 419 ? texts.expired : texts.failed);
        }
    } finally {
        busy = false;
        ui.publish.disabled = ! draft?.publishable;
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
    say(status === 419 ? texts.expired : texts.failed);
}

/* ---------------------------------------------------------------------------------------------------------------
 * Wiring
 * ------------------------------------------------------------------------------------------------------------ */

if (shell && ui.publish && ui.state) {
    ui.publish.addEventListener('click', publish);
    ui.discardConfirm?.addEventListener('click', discard);
    shell.addEventListener('made-cms:saved', refresh);

    // The confirmation closes with Escape and when the editor clicks elsewhere, like a menu.
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && ui.discard?.open) {
            ui.discard.open = false;
            ui.discard.querySelector('summary')?.focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (ui.discard?.open && ! ui.discard.contains(event.target)) {
            ui.discard.open = false;
        }
    });
}
