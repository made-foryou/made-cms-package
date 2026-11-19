/*
 * Made CMS: the card "Hoe je link gedeeld wordt" on the screen of a page (design 23, dark 24).
 *
 * The card shows a shared link as an app shows it (the image that is shared, the domain, and the title and the
 * description from the card "Hoe Google hem ziet", which it follows while the editor types: made-cms:meta), and the
 * share image of the page. "Kiezen uit media" opens the picker (made-cms:pick), "Uploaden" the file chooser of the
 * upload field of one photo (made-cms:upload-choose), whose photo goes to the crop step before it is the choice,
 * "Bijsnijden" the crop step for the chosen photo (made-cms:crop) and the cross takes the image away. A choice is not
 * stored until "Opslaan", a plain form (PUT made-cms/pages/{page}/share-image) that comes back at the card; it waits
 * for an upload on its way and finishes the field first (made-cms:upload-finish).
 *
 * It measures a photo the way MeasureShareImage does (too small, how much of another shape a link cuts away) and warns
 * with the texts of the server; an svg, a document or a video is never the choice: apps show a photo with a link.
 *
 * Its config is JSON on the form ([data-share]): the stored id and file, why that file is not shared, the default image
 * of the site, the address of the media library (for whoever opens it), the measures and the texts.
 */

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

/** Whether a shared link shows a file: a photo, never an svg, a document or a video (ShareImageRefusal). */
function takes(file) {
    return file?.kind === 'image' && file.extension !== 'svg';
}

/** The share of an image a link of 1,91 : 1 cuts away in its middle, and too small, as MeasureShareImage measures. */
function measure(width, height, fit) {
    if (! width || ! height) {
        return { tooSmall: false, cut: 0, axis: null };
    }

    const wanted = fit.width / fit.height;
    const shape = width / height;
    const axis = shape > wanted ? 'x' : 'y';
    const cut = Math.round((1 - (axis === 'x' ? wanted / shape : shape / wanted)) * 100);
    const fits = cut <= fit.tolerance;

    return { tooSmall: width < fit.min_width || height < fit.min_height, cut: fits ? 0 : cut, axis: fits ? null : axis };
}

function share(form) {
    const config = configOf(form);

    if (! config) {
        return;
    }

    const texts = config.texts ?? {};
    const slot = (name) => form.querySelector('[data-slot="share-' + name + '"]');
    const ui = {
        value: slot('value'),
        preview: slot('preview'),
        previewImage: slot('preview-image'),
        title: slot('title'),
        description: slot('description'),
        noDescription: slot('no-description'),
        file: slot('file'),
        mini: slot('mini'),
        thumb: slot('thumb'),
        name: slot('name'),
        meta: slot('meta'),
        alt: slot('alt'),
        altText: slot('alt-text'),
        empty: slot('empty'),
        upload: slot('upload'),
        pick: slot('pick'),
        uploadButton: slot('upload-button'),
        crop: slot('crop'),
        remove: slot('remove'),
        cancel: slot('cancel'),
        notices: slot('notices'),
        save: slot('save'),
        unsaved: slot('unsaved'),
        waiting: slot('waiting'),
        saved: slot('saved'),
        savedText: slot('saved-text'),
    };
    const field = ui.upload?.querySelector('[data-upload]') ?? null;

    const state = {
        stored: config.value ?? null,
        value: config.value ?? null,
        file: config.file ?? null,
        // Why the stored file is not shared; it holds only while the stored file is the choice.
        refusal: config.refusal ?? null,
        // A file the editor tried that a link cannot show: said until the next choice.
        tried: null,
        uploading: false,
        sending: false,
        listed: false,
        // An upload the editor chose past (the picker, the cross): its photo no longer becomes the choice.
        superseded: false,
    };

    const shareable = () => state.file !== null && takes(state.file) && ! (state.value === state.stored && state.refusal !== null);

    function notice(tone, title, text, link = null) {
        const element = document.createElement('div');
        const dot = document.createElement('span');
        const words = document.createElement('span');

        element.className = 'melding' + (tone ? ' ' + tone : '');
        dot.className = 'bol' + (tone ? ' ' + tone : '');
        dot.setAttribute('aria-hidden', 'true');
        words.className = 'melding-tekst';

        if (title) {
            const strong = document.createElement('strong');

            strong.textContent = title;
            words.append(strong, ' ');
        }

        words.append(text);

        if (link) {
            const anchor = document.createElement('a');
            const arrow = document.createElement('span');

            anchor.className = 'tekstlink';
            anchor.href = link.href;
            arrow.setAttribute('aria-hidden', 'true');
            arrow.textContent = '→';
            anchor.append(link.text + ' ', arrow);
            words.append(' ', anchor);
        }

        element.append(dot, words);

        return element;
    }

    /** What the card warns about, in the order and the words of ShowPageResponse::shareNotices(). */
    function notices() {
        if (state.tried !== null) {
            return [notice('alarm', texts.unusable_title, state.tried)];
        }

        if (state.value === state.stored && state.refusal !== null) {
            return [notice('let', texts.unusable_title, texts['refusal_' + state.refusal] ?? '')];
        }

        if (! shareable()) {
            return [config.fallback ? notice('', '', texts.default) : notice('let', '', texts.none)];
        }

        const file = state.file;
        const width = file.image?.width ?? null;
        const height = file.image?.height ?? null;
        const fit = measure(width, height, config.fit);
        const list = [];

        if (fit.tooSmall) {
            list.push(notice('let', texts.small_title, format(texts.small, { width, height })));
        } else if (fit.cut > 0) {
            const words = [format(texts['cut_' + fit.axis], { cut: fit.cut }), file.croppable ? texts.cut_crop : null, texts.go_on];

            list.push(notice('let', texts.cut_title, words.filter(Boolean).join(' ')));
        }

        if (! file.alt || file.alt.trim() === '') {
            const media = config.media_url;

            list.push(notice(
                'let',
                texts.alt_title,
                media === null && file.croppable ? texts.alt_crop : texts.alt,
                media === null ? null : { text: texts.alt_link, href: media + '?file=' + file.id },
            ));
        }

        return list;
    }

    function draw() {
        const file = state.file;
        const shown = shareable();
        const changed = state.value !== state.stored;

        ui.value.value = state.value === null ? '' : String(state.value);

        // The preview shows what a link shows: the chosen photo small, else the default of the site, else nothing.
        const image = shown ? (file.thumb ?? file.url) : (config.fallback ?? null);

        ui.preview.hidden = image === null;

        if (image !== null && ui.previewImage.getAttribute('src') !== image) {
            ui.previewImage.src = image;
        }

        ui.file.hidden = file === null;
        ui.empty.hidden = file !== null;
        ui.remove.hidden = state.value === null;
        ui.crop.hidden = ! (shown && file.croppable === true);

        if (file !== null) {
            const alt = (file.alt ?? '').trim();

            ui.name.textContent = file.name + (file.extension ? '.' + file.extension : '');
            ui.meta.textContent = file.folder ? format(texts.meta, { meta: file.meta, folder: file.folder }) : (file.meta ?? '');
            ui.alt.dataset.alt = alt === '' ? 'missing' : 'set';
            ui.altText.textContent = alt === '' ? texts.no_alt_line : format(texts.alt_line, { alt });

            if (file.thumb && ui.thumb.getAttribute('src') !== file.thumb) {
                ui.thumb.src = file.thumb;
            }

            // The small image has the shape of the photo, and what a link cuts away dims at its edges.
            const width = file.image?.width ?? null;
            const height = file.image?.height ?? null;
            const fit = shown ? measure(width, height, config.fit) : { cut: 0, axis: null };

            ui.mini.dataset.axis = fit.axis ?? '';
            ui.mini.style.setProperty('--cut', (fit.cut / 2) + '%');

            if (width && height) {
                ui.mini.style.setProperty('--ratio', String(width / height));
            } else {
                ui.mini.style.removeProperty('--ratio');
            }
        }

        ui.notices.replaceChildren(...notices());

        ui.pick.disabled = state.uploading;
        ui.uploadButton.disabled = state.uploading;
        ui.cancel.hidden = ! state.sending;
        ui.upload.hidden = ! state.listed;

        ui.save.disabled = ! changed || state.uploading;
        ui.unsaved.hidden = ! changed || state.uploading;
        ui.waiting.hidden = ! state.uploading;

        if (changed) {
            ui.saved?.remove();
            ui.savedText?.remove();
        }
    }

    /** A file the editor chose, from the picker or an upload: the choice when a link can show it. */
    function take(file) {
        if (! file) {
            return;
        }

        if (! takes(file)) {
            state.tried = texts['refusal_' + (file.kind === 'image' ? 'svg' : 'kind')] ?? '';
            draw();

            return;
        }

        state.tried = null;
        state.file = file;
        state.value = file.id;
        draw();
    }

    // The picker opens with the photo of the card chosen and gives back one photo.
    ui.pick.addEventListener('click', () => {
        document.querySelector('[data-media-picker]')?.dispatchEvent(new CustomEvent('made-cms:pick', {
            detail: {
                field: texts.field,
                place: texts.place,
                hint: texts.hint,
                file: state.file?.image ? state.file : null,
                many: null,
                kinds: null,
                opener: ui.pick,
                choose: (files) => {
                    if (state.uploading) {
                        state.superseded = true;
                    }

                    take(files[0] ?? null);
                },
            },
        }));
    });

    // The crop step changes the file itself, on every page; the choice stays its id.
    ui.crop.addEventListener('click', () => {
        if (shareable() && state.file.croppable) {
            document.querySelector('[data-media-crop]')?.dispatchEvent(new CustomEvent('made-cms:crop', {
                detail: { files: [state.file], opener: ui.crop },
            }));
        }
    });

    document.addEventListener('made-cms:media-changed', (event) => {
        const file = event.detail?.file;

        if (file && state.file && file.id === state.file.id) {
            state.file = { ...state.file, ...file };
            draw();
        }
    });

    ui.remove.addEventListener('click', () => {
        if (state.uploading) {
            state.superseded = true;
        }

        state.tried = null;
        state.file = null;
        state.value = null;
        draw();
        ui.pick.focus();
    });

    if (field) {
        ui.uploadButton.addEventListener('click', () => {
            state.superseded = false;
            field.dispatchEvent(new CustomEvent('made-cms:upload-choose'));
        });

        ui.cancel.addEventListener('click', () => field.dispatchEvent(new CustomEvent('made-cms:upload-stop')));

        // The field shows only while it has a file to tell about, and the card waits while one travels.
        field.addEventListener('made-cms:upload', (event) => {
            const detail = event.detail ?? {};

            state.uploading = detail.busy === true;
            state.sending = detail.sending === true;
            state.listed = (detail.total ?? 0) > 0;
            draw();
        });

        field.addEventListener('made-cms:uploaded', (event) => {
            if (! state.superseded) {
                take(event.detail?.file ?? null);
            }
        });

        // Once the field is left, the photo has its alt text as stored (asked in the crop step or the field).
        field.addEventListener('made-cms:upload-closed', (event) => {
            const stored = (event.detail?.files ?? []).find((file) => file.id === state.file?.id);

            if (stored) {
                state.file = { ...state.file, ...stored };
            }

            state.listed = false;
            draw();
        });

        // The picker remembers the folder the editor opened; an upload of the card goes there too.
        document.addEventListener('made-cms:media-folder', (event) => {
            field.dispatchEvent(new CustomEvent('made-cms:upload-folder', { detail: { folder: event.detail?.folder ?? null } }));
        });
    }

    // The title and the description come from the card of Google, also while the editor types there.
    document.addEventListener('made-cms:meta', (event) => {
        const title = event.detail?.title ?? '';
        const description = event.detail?.description ?? '';

        ui.title.textContent = title;
        ui.description.textContent = description;
        ui.description.hidden = description === '';
        ui.noDescription.hidden = description !== '';
    });

    // Saving waits for an upload on its way and stores a typed alt text of the field first.
    let finishing = false;

    form.addEventListener('submit', async (event) => {
        if (finishing) {
            return;
        }

        event.preventDefault();

        if (state.uploading || state.value === state.stored) {
            return;
        }

        const waits = [];

        field?.dispatchEvent(new CustomEvent('made-cms:upload-finish', { detail: { wait: (promise) => waits.push(promise) } }));
        await Promise.all(waits);

        finishing = true;
        ui.value.value = state.value === null ? '' : String(state.value);
        form.submit();
    });

    draw();
}

document.querySelectorAll('form[data-share]').forEach(share);
