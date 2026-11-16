/*
 * Made CMS: the screen of the media library (design 13.1b–z, 14.1b–z), around the list of media.js.
 *
 * - The side column ([data-media-details]) shows the file the list chose (made-cms:media-chosen): its thumbnail, what
 *   it is, its alt text with "Opslaan" (Enter saves too; choosing another file stores a typed text first and moves
 *   only once it is stored, so a refusal, a network that fails or an expired session keeps the choice where it is,
 *   with its message), the pages it is used on from GET made-cms/media/{media} with
 *   a link to their edit shell, and "Bijsnijden" for a photo, which opens the crop step (made-cms:crop). A stored alt
 *   text bubbles up as made-cms:media-changed {file}, like a crop does, so the list shows it in its tile and its numbers.
 * - "Uploaden" ([data-library-upload]) opens the file chooser of the upload field of the list, and files dragged from
 *   the computer onto the library land on a layer that hands them to the field (made-cms:upload-take). The field shows
 *   only while it has files to tell about.
 * - An address with ?file= opens with that file in the side column, also when its tile is not on the first page.
 *
 * Its config is JSON on the library ([data-media-library]): the addresses, the names of the kinds and its texts.
 */

const HEADERS = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

function format(text, values = {}) {
    return String(text ?? '').replace(/:([a-z_]+)/g, (match, key) => (key in values ? String(values[key]) : match));
}

function configOf(element) {
    try {
        return JSON.parse(element.dataset.config ?? '');
    } catch {
        return null;
    }
}

/** The message of a refusal keyed by a field, else null. */
async function refusal(response, field) {
    const data = await response.json().catch(() => null);
    const message = data?.errors?.[field];

    return Array.isArray(message) ? String(message[0]) : null;
}

function library(root) {
    const settings = configOf(root);
    const list = root.querySelector('[data-media]');
    const aside = root.querySelector('[data-media-details]');

    if (! settings || ! list || ! aside) {
        return;
    }

    const texts = settings.texts ?? {};
    const listSettings = configOf(list) ?? {};
    const field = list.querySelector('[data-upload]');
    const fold = list.querySelector('[data-slot="media-upload"]');
    const drop = root.querySelector('[data-slot="library-drop"]');
    const slot = (name, scope = aside) => scope.querySelector('[data-slot="details-' + name + '"]');
    const ui = {
        nothing: slot('nothing'),
        file: slot('file'),
        back: slot('back'),
        mini: slot('mini'),
        thumb: slot('thumb'),
        extension: slot('extension'),
        noAlt: slot('no-alt'),
        name: slot('name'),
        line: slot('line'),
        alt: slot('alt'),
        hint: slot('alt-hint'),
        save: slot('save'),
        changed: slot('changed'),
        status: slot('status'),
        heading: slot('usage-heading'),
        loading: slot('loading'),
        loadingText: slot('loading-text'),
        nowhere: slot('nowhere'),
        pages: slot('pages'),
        actions: slot('actions'),
        crop: slot('crop'),
    };
    const pageTemplate = aside.querySelector('template[data-details-page]');

    if (Object.values(ui).some((element) => element === null) || ! pageTemplate) {
        return;
    }

    const state = {
        // The file the side column shows, as the media endpoints give it.
        file: null,
        // The request for the places of the file.
        places: null,
        folder: listSettings.folder ?? null,
    };
    // The alt texts on their way to the server, by the id of their file: {alt, promise}. A file the editor comes back to
    // while its text travels shows that text, never the older one the list still holds.
    const saving = new Map();
    const saveLabel = ui.save.textContent;

    const address = (pattern, file) => pattern.replace(':media', String(file.id));
    const typed = () => ui.alt.value.trim();
    const stored = () => (state.file?.alt ?? '').trim();
    const changed = () => state.file !== null && typed() !== stored();
    // Longer than the server takes: said at once, and the choice stays until it is shorter.
    const tooLong = () => typed().length > (settings.alt_max ?? 255);

    /** The hint under the alt text: what it is for this kind, or that an image still has none; an error in its place. */
    function hint(error = null) {
        const kind = state.file?.kind ?? 'image';
        const empty = kind === 'image' && stored() === '' && typed() === '';

        ui.hint.textContent = error ?? (kind === 'image' ? (empty ? texts.alt_hint_empty : texts.alt_hint_image) : texts['alt_hint_' + kind]);
        ui.hint.classList.toggle('fout', error !== null);
        ui.hint.classList.toggle('leeg', error === null && empty);
        ui.alt.classList.toggle('fout', error !== null);
        error === null ? ui.hint.removeAttribute('role') : ui.hint.setAttribute('role', 'alert');
    }

    /** The button and the line next to it: unchanged, changed, busy, saved. */
    function moment(name) {
        ui.save.disabled = name !== 'changed' && name !== 'error';
        ui.save.textContent = name === 'busy' ? texts.saving : saveLabel;
        ui.alt.disabled = name === 'busy';
        ui.changed.hidden = name !== 'changed';
        ui.changed.textContent = texts.alt_changed ?? '';
        ui.status.textContent = name === 'saved' ? texts.saved : '';
        ui.status.classList.toggle('zichtbaar', name === 'saved');
    }

    function draw(file) {
        const kind = file.kind ?? 'image';
        const noAlt = kind === 'image' && (file.alt ?? '') === '';
        const thumb = file.thumb ?? null;
        const pending = saving.get(file.id) ?? null;

        state.file = file;
        ui.nothing.hidden = true;
        ui.file.hidden = false;
        ui.mini.dataset.kind = kind;
        ui.thumb.hidden = thumb === null;

        if (thumb !== null && ui.thumb.getAttribute('src') !== thumb) {
            const focal = file.image?.focal ?? { x: 0.5, y: 0.5 };

            // The thumbnail, as the tiles show it: the photo itself belongs to the media element of a page.
            ui.thumb.src = thumb;
            ui.thumb.style.objectPosition = (focal.x * 100) + '% ' + (focal.y * 100) + '%';
        }

        ui.extension.textContent = file.extension ?? '';
        ui.noAlt.hidden = ! noAlt;
        ui.name.textContent = file.file ?? file.name;
        ui.line.textContent = [
            settings.kinds?.[kind],
            file.extension,
            file.meta,
            file.folder ? format(texts.detail_folder, { folder: file.folder }) : null,
        ].filter(Boolean).join(' · ');
        ui.alt.value = pending?.alt ?? file.alt ?? '';
        ui.alt.placeholder = texts['alt_placeholder_' + kind] ?? '';
        ui.actions.hidden = file.croppable !== true;
        hint();
        moment(pending ? 'busy' : 'unchanged');
    }

    function nothing() {
        state.file = null;
        ui.nothing.hidden = false;
        ui.file.hidden = true;
    }

    /** The pages the file is on, from the server; a newer file drops an older answer. */
    async function usage(file) {
        state.places?.abort();

        const controller = new AbortController();

        state.places = controller;
        ui.heading.textContent = texts.usage ?? ui.heading.textContent;
        ui.loading.hidden = false;
        ui.loadingText.textContent = texts.loading;
        ui.nowhere.hidden = true;
        ui.pages.replaceChildren();

        try {
            const response = await fetch(address(settings.urls.show, file), { credentials: 'same-origin', headers: HEADERS, signal: controller.signal });
            const data = response.ok ? await response.json() : null;

            if (! data) {
                ui.loadingText.textContent = response.status === 401 || response.status === 419 ? texts.expired : texts.usage_failed;

                return;
            }

            ui.loading.hidden = true;
            ui.heading.textContent = data.heading ?? '';
            ui.nowhere.hidden = (data.pages ?? []).length > 0;
            ui.pages.replaceChildren(...(data.pages ?? []).map(page));
        } catch (error) {
            if (error?.name !== 'AbortError') {
                ui.loadingText.textContent = texts.usage_failed;
            }
        } finally {
            if (state.places === controller) {
                state.places = null;
            }
        }
    }

    function page(data) {
        const element = pageTemplate.content.firstElementChild.cloneNode(true);
        const part = (name) => slot('page-' + name, element);
        const link = part('link');
        const note = part('note');

        if (data.url) {
            link.href = data.url;
            link.setAttribute('aria-label', format(texts.edit, { title: data.title }));
            part('title').textContent = data.title;
            part('name').remove();
        } else {
            link.remove();
            part('name').hidden = false;
            part('name').textContent = data.title;
        }

        note.hidden = ! data.note;
        note.textContent = data.note ?? '';

        for (const place of data.places ?? []) {
            const line = document.createElement('span');

            line.className = 'bibliotheek-plek';
            line.textContent = place.where;

            if (place.note) {
                const own = document.createElement('span');

                own.className = 'bibliotheek-noot';
                own.textContent = place.note;
                line.append(' ', own);
            }

            part('places').append(line);
        }

        return element;
    }

    function show(file) {
        draw(file);
        usage(file);
    }

    /** Stores the typed alt text of the file it shows. False when the server refused it, which then stays with its error. */
    function save() {
        const file = state.file;

        if (file === null || ! changed()) {
            return Promise.resolve(true);
        }

        if (saving.has(file.id)) {
            return saving.get(file.id).promise;
        }

        if (tooLong()) {
            moment('error');
            hint(texts.alt_long);
            ui.alt.focus();

            return Promise.resolve(false);
        }

        const entry = { alt: typed(), promise: null };

        saving.set(file.id, entry);
        moment('busy');
        entry.promise = (async () => {
            try {
                const response = await fetch(address(settings.urls.alt, file), {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: { ...HEADERS, 'Content-Type': 'application/json', 'X-CSRF-TOKEN': settings.csrf_token ?? '' },
                    body: JSON.stringify({ alt: entry.alt }),
                });

                if (! response.ok) {
                    const message = response.status === 422 ? await refusal(response, 'alt') : null;

                    if (state.file?.id === file.id) {
                        moment('error');
                        hint(message ?? (response.status === 401 || response.status === 419 ? texts.expired : texts.alt_failed));
                        ui.alt.focus();
                    }

                    return false;
                }

                const saved = (await response.json()).file;

                // The field shows the text as it is stored now, also after the column showed the file again meanwhile.
                if (state.file?.id === file.id) {
                    state.file = saved;
                    ui.alt.value = saved.alt ?? '';
                    ui.noAlt.hidden = ! (saved.kind === 'image' && (saved.alt ?? '') === '');
                    hint();
                    moment('saved');
                }

                root.dispatchEvent(new CustomEvent('made-cms:media-changed', { bubbles: true, detail: { file: saved } }));

                return true;
            } catch {
                if (state.file?.id === file.id) {
                    moment('error');
                    hint(texts.alt_failed);
                    ui.alt.focus();
                }

                return false;
            } finally {
                saving.delete(file.id);

                if (state.file?.id === file.id && ui.alt.disabled) {
                    ui.alt.disabled = false;
                }
            }
        })();

        return entry.promise;
    }

    // The side column follows the choice of the list, but a typed alt text is stored first: the choice moves once it
    // is, and stays where it is with the message when the server refuses it, the network fails or the session expired.
    // A text longer than the server takes holds it at once.
    list.addEventListener('made-cms:media-choosing', (event) => {
        if (! changed()) {
            return;
        }

        if (tooLong()) {
            event.preventDefault();
            moment('error');
            hint(texts.alt_long);
            ui.alt.focus();

            return;
        }

        event.detail?.wait?.(save());
    });
    list.addEventListener('made-cms:media-chosen', (event) => {
        const file = event.detail?.file;

        if (! file) {
            nothing();

            return;
        }

        show(file);

        // On a phone the side column is below the files: it comes into view.
        if (window.matchMedia('(max-width: 600px)').matches) {
            aside.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        }
    });

    ui.alt.addEventListener('input', () => {
        // An error stays until the text is one the server takes.
        if (ui.hint.classList.contains('fout') && changed() && tooLong()) {
            moment('error');

            return;
        }

        hint();
        moment(changed() ? 'changed' : 'unchanged');
    });
    ui.alt.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && ! event.shiftKey) {
            event.preventDefault();
            save();
        }
    });
    ui.save.addEventListener('click', () => save());

    ui.crop.addEventListener('click', async () => {
        const step = document.querySelector('[data-media-crop]');

        if (! step || state.file === null || ! (await save())) {
            return;
        }

        step.dispatchEvent(new CustomEvent('made-cms:crop', { detail: { files: [state.file], opener: ui.crop } }));
    });

    // A file stored elsewhere on the page (the crop step, the upload field): the side column shows it as it is now.
    document.addEventListener('made-cms:media-changed', (event) => {
        const file = event.detail?.file;

        if (file && state.file?.id === file.id && event.target !== root) {
            show(file);
        }
    });
    field?.addEventListener('made-cms:upload-closed', (event) => {
        const file = (event.detail?.files ?? []).find((candidate) => candidate.id === state.file?.id);

        if (file) {
            show(file);
        }
    });

    // "Uploaden" opens the file chooser of the field, in the click of the editor, as the browser asks.
    document.addEventListener('click', (event) => {
        if (field && event.target instanceof Element && event.target.closest('[data-library-upload]')) {
            field.dispatchEvent(new CustomEvent('made-cms:upload-choose'));
        }
    });

    // The field shows while it has files to tell about, and folds away once it is at rest.
    field?.addEventListener('made-cms:upload', (event) => {
        const detail = event.detail ?? {};

        if (fold) {
            fold.hidden = ! (detail.busy || (detail.total ?? 0) > 0 || (detail.failed ?? 0) > 0);
        }
    });

    list.addEventListener('made-cms:media-folder', (event) => {
        state.folder = event.detail?.folder ?? null;
    });

    // Files from the computer land anywhere on the library; the layer says where they go.
    const carriesFiles = (event) => [...(event.dataTransfer?.types ?? [])].includes('Files');

    function hover(over, transfer = null) {
        if (! drop) {
            return;
        }

        if (over) {
            const count = [...(transfer?.items ?? [])].filter((item) => item.kind === 'file').length;
            const values = { count, limits: settings.limits ?? '' };

            drop.querySelector('[data-slot="library-drop-title"]').textContent = state.folder ? format(texts.drop_title_folder, { folder: state.folder.name }) : texts.drop_title;
            drop.querySelector('[data-slot="library-drop-line"]').textContent = format(count === 0 ? texts.drop_unknown : (count === 1 ? texts.drop_one : texts.drop_many), values);
        }

        drop.hidden = ! over;
        root.toggleAttribute('data-drop', over);
    }

    if (field) {
        root.addEventListener('dragenter', (event) => {
            if (carriesFiles(event)) {
                event.preventDefault();
                hover(true, event.dataTransfer);
            }
        });
        root.addEventListener('dragover', (event) => {
            if (carriesFiles(event)) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'copy';
                hover(true, event.dataTransfer);
            }
        });
        root.addEventListener('dragleave', (event) => {
            if (! root.contains(event.relatedTarget)) {
                hover(false);
            }
        });
        root.addEventListener('drop', (event) => {
            hover(false);

            // The upload field took a drop on itself already.
            if (! carriesFiles(event) || event.defaultPrevented) {
                return;
            }

            event.preventDefault();
            field.dispatchEvent(new CustomEvent('made-cms:upload-take', { detail: { transfer: event.dataTransfer } }));
        });
    }

    // An address with ?file= whose tile is not on the first page still opens that file in the side column.
    document.addEventListener('DOMContentLoaded', async () => {
        const id = listSettings.chosen;

        if (state.file !== null || id === null || id === undefined) {
            return;
        }

        try {
            const response = await fetch(address(settings.urls.show, { id }), { credentials: 'same-origin', headers: HEADERS });
            const data = response.ok ? await response.json() : null;

            if (data?.file && state.file === null) {
                draw(data.file);
                usage(data.file);
            }
        } catch {
            // The file left the library: the side column says what choosing does.
        }
    });
}

document.querySelectorAll('[data-media-library]').forEach(library);
