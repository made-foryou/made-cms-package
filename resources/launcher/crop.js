/*
 * Made CMS: the crop step of a photo (design 13.6, 14.6).
 *
 * [data-media-crop] is a modal <dialog> that gets a photo ready for the site, one file after the other: the whole
 * original on a stage (made-cms/media/{id}/original, upright and scaled down, drawn on a canvas), the frame of the crop
 * with its corners and ratios, turning, mirroring, the focal point, and on the right "Verkleinen voor het web", the
 * name, the alt text and (for an editor who opens the library) the folder. Its fixed texts are in its markup
 * (cms/partials/crop.blade.php), its config is JSON on itself (data-config).
 *
 * The crop is in pixels of the original as the editor sees it: upright, turned clockwise by r (0, 90, 180, 270), then
 * mirrored left to right when flipped; the server takes it back onto the original (CropArea). The focal point is kept
 * in the same pixels while the editor works and sent as 0 to 1 of the crop, the photo that comes out.
 *
 * A host opens it with made-cms:crop on the dialog: {files, opener, upload, done(results)}, the files as the upload
 * field gets one. "Opslaan" stores what changed of the file (its name, alt text, folder and crop, one request each, in
 * that order) and goes to the next file; "Zo laten, zonder snijden", the cross and Escape store the fields on the
 * right only and leave the photo as it is; the cross and Escape stop after the file. When it closes, done gets
 * [{file, handled}] for every file in the order it was given: handled when the editor saw it and its texts are stored.
 * The focus goes back to opener.
 *
 * What a host hears (bubbling from the dialog): made-cms:media-changed {file} for every file of which anything was
 * stored, the file as it is now.
 */

const HEADERS = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

/** The ratios of the frame: width over height; "free" keeps any size, "original" the one of the turned original. */
const RATIOS = { '16:9': 16 / 9, '4:3': 4 / 3, '3:2': 3 / 2, '1:1': 1 };

/** The smallest frame, in pixels on the screen. */
const SMALLEST = 32;

/** The share of the original an arrow key moves the frame, a corner or the focal point; with Shift the larger one. */
const STEP = 0.01;
const LARGE_STEP = 0.1;

/** How long the editor pauses moving with the keyboard before the new crop is read out, in ms. */
const READ_AFTER = 300;

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

const clamp = (value, low, high) => Math.min(Math.max(value, low), high);

/** The first message of a 422, with its key. */
function refusal(data) {
    const [key, messages] = Object.entries(data?.errors ?? {})[0] ?? [];

    return Array.isArray(messages) && typeof messages[0] === 'string' ? { key, message: messages[0] } : null;
}

function step(dialog) {
    const settings = configOf(dialog);

    if (! settings) {
        return;
    }

    const texts = settings.texts ?? {};
    const slot = (name) => dialog.querySelector('[data-slot="crop-' + name + '"]');
    const ui = {
        eyebrow: slot('eyebrow'),
        line: slot('line'),
        used: slot('used'),
        close: slot('close'),
        stage: slot('stage'),
        picture: slot('picture'),
        canvas: slot('canvas'),
        frame: slot('frame'),
        focal: slot('focal'),
        area: slot('area'),
        loading: slot('loading'),
        left: slot('left'),
        right: slot('right'),
        flip: slot('flip'),
        reset: slot('reset'),
        error: slot('error'),
        scaleDown: slot('scale-down'),
        weight: slot('weight'),
        weightFrom: slot('weight-from'),
        weightTo: slot('weight-to'),
        name: slot('name'),
        nameError: slot('name-error'),
        alt: slot('alt'),
        altError: slot('alt-error'),
        stepsRow: slot('steps-row'),
        steps: slot('steps'),
        step: slot('step'),
        status: slot('status'),
        keep: slot('keep'),
        save: slot('save'),
        announce: slot('announce'),
    };
    const folder = slot('folder');
    const folderError = slot('folder-error');
    const corners = [...dialog.querySelectorAll('[data-corner]')];
    const ratios = [...dialog.querySelectorAll('[data-ratio]')];

    if (Object.values(ui).some((element) => element === null)) {
        return;
    }

    /** What a host asked for: {files, opener, upload, done}. */
    let request = null;
    let queue = [];
    let results = [];
    let index = 0;
    // The file being worked on: {file, details, picture, bytes, base}, base being what is stored now.
    let current = null;
    // The crop as the editor sees it now: the turn, the mirror, the frame and the focal point in pixels of the view.
    let view = null;
    let ratio = 'free';
    let busy = false;
    let loaded = 0;
    let reading = null;

    const working = () => current !== null && current.details !== null;
    const turned = (rotation) => (rotation === 90 || rotation === 270
        ? { width: current.details.original.height, height: current.details.original.width }
        : { width: current.details.original.width, height: current.details.original.height });
    const size = () => turned(view.rotation);
    const decimals = (value) => value.toFixed(1).replace('.', texts.decimal ?? '.');
    const weigh = (bytes) => (bytes < 1_000_000
        ? format(texts.kb, { size: Math.max(1, Math.round(bytes / 1000)) })
        : format(texts.mb, { size: decimals(bytes / 1_000_000) }));
    const url = (name, id) => (settings.urls?.[name] ?? '').replace(':media', String(id));

    function tell(name, detail) {
        dialog.dispatchEvent(new CustomEvent(name, { bubbles: true, detail }));
    }

    function announce(text) {
        ui.announce.textContent = '';
        requestAnimationFrame(() => {
            ui.announce.textContent = text;
        });
    }

    /** Reads out the crop or the focal point once the editor stops moving it with the keyboard. */
    function readOut(what) {
        clearTimeout(reading);
        reading = setTimeout(() => announce(what === 'focal' ? focalText() : areaText()), READ_AFTER);
    }

    async function send(target, method, body = null) {
        try {
            const response = await fetch(target, {
                method,
                credentials: 'same-origin',
                headers: { ...HEADERS, ...(body === null ? {} : { 'Content-Type': 'application/json' }), 'X-CSRF-TOKEN': settings.csrf_token ?? '' },
                body: body === null ? null : JSON.stringify(body),
            });
            const data = await response.json().catch(() => null);

            return { ok: response.ok, status: response.status, data };
        } catch {
            return { ok: false, status: 0, data: null };
        }
    }

    /** The whole original, upright and scaled down, with its bytes for the estimate. */
    async function preview(id) {
        try {
            const response = await fetch(url('original', id), { credentials: 'same-origin', headers: HEADERS });

            if (! response.ok) {
                const data = await response.json().catch(() => null);

                return { picture: null, bytes: 0, message: refusal(data)?.message ?? texts.failed };
            }

            const blob = await response.blob();

            // A bitmap, never a blob: address, which the content security policy of a host may refuse for images.
            return { picture: await createImageBitmap(blob), bytes: blob.size, message: null };
        } catch {
            return { picture: null, bytes: 0, message: texts.failed };
        }
    }

    async function folders() {
        if (! folder || ! settings.urls?.folders) {
            return;
        }

        const answer = await send(settings.urls.folders, 'GET');
        const options = [new Option(texts.all_files ?? '', '')];

        for (const each of answer.data?.folders ?? []) {
            options.push(new Option(each.name, String(each.id)));
        }

        const chosen = folder.value;

        folder.replaceChildren(...options);
        folder.value = chosen;
    }

    // The ratio a frame of this size has: "original" for the whole view, a fixed one within a pixel, else "free".
    function ratioOf(frame) {
        const { width, height } = size();

        if (frame.x === 0 && frame.y === 0 && frame.width === width && frame.height === height) {
            return 'original';
        }

        return Object.keys(RATIOS).find((key) => Math.abs(frame.width - frame.height * RATIOS[key]) <= 1) ?? 'free';
    }

    const proportion = (key) => (key === 'original' ? size().width / size().height : RATIOS[key] ?? null);

    /** The largest frame of a ratio around a centre, inside the view. */
    function fit(key, centre) {
        const { width, height } = size();
        const wanted = proportion(key);

        if (wanted === null) {
            return { ...view.frame };
        }

        let w = Math.floor(Math.min(width, height * wanted));
        let h = Math.round(w / wanted);

        if (h > height) {
            h = height;
            w = Math.round(h * wanted);
        }

        return {
            x: clamp(Math.round(centre.x - w / 2), 0, width - w),
            y: clamp(Math.round(centre.y - h / 2), 0, height - h),
            width: w,
            height: h,
        };
    }

    /** The focal point stays inside the frame. */
    function keepFocal() {
        const { frame, focal } = view;

        view.focal = {
            x: clamp(focal.x, frame.x, frame.x + frame.width),
            y: clamp(focal.y, frame.y, frame.y + frame.height),
        };
    }

    // How many pixels of the screen one pixel of the view takes, and the smallest frame in pixels of the view.
    let scale = 1;
    const smallest = () => Math.min(Math.ceil(SMALLEST / scale), size().width, size().height);

    function areaText() {
        const label = ratio === 'free' ? texts.area_free : (ratio === 'original' ? texts.area_original : ratio);

        return format(texts.area, { ratio: label, width: view.frame.width, height: view.frame.height });
    }

    function focalText() {
        const { frame, focal } = view;

        return format(texts.focal_at, {
            x: Math.round(((focal.x - frame.x) / frame.width) * 100),
            y: Math.round(((focal.y - frame.y) / frame.height) * 100),
        });
    }

    /** The size of the photo that comes out: scaled down to the maximum width unless the editor switched that off. */
    function output() {
        const { width, height } = view.frame;
        const most = settings.max_width ?? Infinity;

        return ui.scaleDown.checked && width > most ? { width: most, height: Math.round((height * most) / width) } : { width, height };
    }

    /** "4,2 MB jpg / ± 380 kB WebP": the estimate is the bytes per pixel of the preview times the pixels of the crop. */
    function drawWeight() {
        const original = current.details.original;

        if (! current.picture || current.bytes <= 0) {
            ui.weight.hidden = true;

            return;
        }

        const perPixel = current.bytes / (current.picture.width * current.picture.height);
        const out = output();

        ui.weight.hidden = false;
        ui.weightFrom.hidden = original.size === null;
        ui.weightFrom.textContent = original.size === null ? '' : format(texts.weight_from, { size: weigh(original.size), extension: original.extension });
        ui.weightTo.textContent = format(texts.weight_to, { size: weigh(perPixel * out.width * out.height), format: settings.format ?? '' });
    }

    /** The stage: the original turned and mirrored on the canvas, the frame and the focal point on top of it. */
    function draw() {
        if (! working() || ! current.picture) {
            return;
        }

        const { width, height } = size();
        const room = { width: ui.stage.clientWidth - 32, height: ui.stage.clientHeight - 32 };

        if (room.width <= 0 || room.height <= 0) {
            return;
        }

        scale = Math.min(room.width / width, room.height / height);

        const shown = { width: width * scale, height: height * scale };
        const ratioOfScreen = window.devicePixelRatio || 1;
        const context = ui.canvas.getContext('2d');
        const original = current.details.original;

        ui.picture.style.width = shown.width + 'px';
        ui.picture.style.height = shown.height + 'px';
        ui.canvas.width = Math.round(shown.width * ratioOfScreen);
        ui.canvas.height = Math.round(shown.height * ratioOfScreen);

        // Read from the inside out: the picture is turned around its centre, then mirrored, then moved to the middle.
        context.setTransform(ratioOfScreen, 0, 0, ratioOfScreen, 0, 0);
        context.clearRect(0, 0, shown.width, shown.height);
        context.translate(shown.width / 2, shown.height / 2);

        if (view.flip) {
            context.scale(-1, 1);
        }

        context.rotate((view.rotation * Math.PI) / 180);
        context.drawImage(current.picture, (-original.width * scale) / 2, (-original.height * scale) / 2, original.width * scale, original.height * scale);

        drawFrame();
    }

    function drawFrame() {
        const { frame, focal } = view;

        ui.frame.style.left = frame.x * scale + 'px';
        ui.frame.style.top = frame.y * scale + 'px';
        ui.frame.style.width = frame.width * scale + 'px';
        ui.frame.style.height = frame.height * scale + 'px';
        ui.focal.style.left = focal.x * scale + 'px';
        ui.focal.style.top = focal.y * scale + 'px';
        ui.area.textContent = areaText();
        ui.flip.setAttribute('aria-pressed', String(view.flip));
        ratios.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.ratio === ratio)));
        drawWeight();
    }

    function changed() {
        keepFocal();
        drawFrame();
    }

    // The fields on the right: name, alt text and folder, with the error of each.
    const fields = () => [
        { key: 'name', input: ui.name, error: ui.nameError },
        { key: 'alt', input: ui.alt, error: ui.altError },
        ...(folder ? [{ key: 'folder', input: folder, error: folderError }] : []),
    ];

    function refuse(key, message) {
        for (const field of fields()) {
            const refused = field.key === key;

            field.input.closest('.veld')?.classList.toggle('fout', refused);
            field.input.toggleAttribute('aria-invalid', refused);
            field.error.hidden = ! refused;
            field.error.textContent = refused ? message : '';
        }

        const ofCrop = key !== null && ! fields().some((field) => field.key === key);

        ui.error.hidden = ! ofCrop;
        ui.error.textContent = ofCrop ? message : '';
    }

    /** The focus goes to what was refused, once the fields take it again: the field, else the frame. */
    function focusRefused() {
        const refused = fields().find((field) => ! field.error.hidden)?.input ?? (ui.error.hidden ? null : ui.frame);

        refused?.focus();
    }

    function lock(locked) {
        busy = locked;
        dialog.toggleAttribute('aria-busy', locked);

        const ready = working();
        const drawn = ready && current.picture !== null;

        [ui.left, ui.right, ui.flip, ui.reset, ui.scaleDown, ...ratios, ...corners, ui.focal].forEach((element) => {
            element.disabled = locked || ! drawn;
        });
        [ui.name, ui.alt, ...(folder ? [folder] : [])].forEach((element) => {
            element.disabled = locked || ! ready;
        });
        ui.save.disabled = locked || ! ready;
        ui.keep.disabled = locked;
    }

    function drawHead() {
        const count = queue.length;
        const file = current?.file;

        ui.eyebrow.textContent = request?.upload
            ? (count === 1 ? texts.eyebrow_upload_one : format(texts.eyebrow_upload, { index: index + 1, count }))
            : texts.eyebrow;
        ui.line.textContent = current?.details?.line ?? file?.name ?? '';
        ui.used.hidden = ! current?.details?.used;
        ui.used.textContent = current?.details?.used ?? '';

        ui.stepsRow.hidden = count < 2;
        ui.steps.replaceChildren(...queue.map((each, position) => {
            const bar = document.createElement('span');

            bar.className = 'stap' + (position < index ? ' gedaan' : (position === index ? ' actief' : ''));

            return bar;
        }));

        const next = queue[index + 1];

        ui.step.textContent = next
            ? format(texts.step_next, { index: index + 1, count, name: next.name ?? '' })
            : format(texts.step, { index: index + 1, count });
        ui.save.textContent = next ? texts.save_next : texts.save;
    }

    /** Loads the file at this place in the queue: what the server says of it and the preview of its original. */
    async function load(position) {
        const serial = ++loaded;
        const file = queue[position];

        index = position;
        current?.picture?.close?.();
        current = { file, details: null, picture: null, bytes: 0, base: null };
        view = null;
        ui.picture.hidden = true;
        ui.loading.hidden = false;
        ui.loading.textContent = texts.loading;
        refuse(null, '');
        drawHead();
        lock(true);

        const [details, shown] = await Promise.all([send(url('details', file.id), 'GET'), preview(file.id)]);

        if (serial !== loaded || ! dialog.open) {
            return;
        }

        if (! details.ok || ! details.data?.original) {
            ui.loading.textContent = details.status === 401 || details.status === 419 ? texts.expired : texts.failed;
            lock(false);

            return;
        }

        begin(details.data, shown);
    }

    function begin(details, shown) {
        const file = details.file;
        const crop = details.crop;
        const focal = file.image?.focal ?? { x: 0.5, y: 0.5 };

        current = {
            file,
            details,
            picture: shown.picture,
            bytes: shown.bytes,
            base: {
                name: file.name,
                alt: file.alt ?? '',
                folder: details.folder === null ? '' : String(details.folder),
                crop: { ...crop },
                scaleDown: details.scale_down,
                focal: { x: focal.x, y: focal.y },
            },
        };
        view = {
            rotation: crop.rotation,
            flip: crop.flip,
            frame: { x: crop.x, y: crop.y, width: crop.width, height: crop.height },
            focal: { x: crop.x + focal.x * crop.width, y: crop.y + focal.y * crop.height },
        };
        ratio = ratioOf(view.frame);

        ui.name.value = current.base.name;
        ui.alt.value = current.base.alt;
        ui.scaleDown.checked = current.base.scaleDown;

        if (folder) {
            if (! [...folder.options].some((option) => option.value === current.base.folder)) {
                folder.append(new Option(file.folder ?? '', current.base.folder));
            }

            folder.value = current.base.folder;
        }

        drawHead();
        ui.loading.hidden = shown.picture !== null;
        ui.loading.textContent = shown.message ?? '';
        ui.picture.hidden = shown.picture === null;
        lock(false);
        draw();
        drawWeight();

        if (shown.picture) {
            ui.frame.focus();
        } else {
            ui.name.focus();
        }
    }

    /** What the editor changed of the crop, the focal point and the scaling since it was stored. */
    function cropChanged() {
        const { base } = current;
        const { frame, focal } = view;
        const fx = (focal.x - frame.x) / frame.width;
        const fy = (focal.y - frame.y) / frame.height;

        return view.rotation !== base.crop.rotation || view.flip !== base.crop.flip
            || frame.x !== base.crop.x || frame.y !== base.crop.y || frame.width !== base.crop.width || frame.height !== base.crop.height
            || ui.scaleDown.checked !== base.scaleDown
            || Math.abs(fx - base.focal.x) > 0.00005 || Math.abs(fy - base.focal.y) > 0.00005;
    }

    /**
     * Stores what changed of the file, one request after the other: name, alt text, folder, then the crop when the
     * editor saves the photo too. A refusal stays at its field. True when everything is stored.
     */
    async function store(withCrop) {
        const { file, base } = current;
        const { frame, focal } = view;
        const name = ui.name.value.trim();
        const alt = ui.alt.value.trim();
        const steps = [];

        if (name !== base.name) {
            steps.push(['name', 'PUT', { name }]);
        }

        if (alt !== base.alt) {
            steps.push(['alt', 'PUT', { alt }]);
        }

        if (folder && folder.value !== base.folder) {
            steps.push(['move', 'PUT', { folder: folder.value === '' ? null : Number(folder.value) }]);
        }

        if (withCrop && current.picture && cropChanged()) {
            steps.push(['crop', 'POST', {
                crop: { x: frame.x, y: frame.y, width: frame.width, height: frame.height, rotation: view.rotation, flip: view.flip },
                focal: {
                    x: clamp(Number(((focal.x - frame.x) / frame.width).toFixed(4)), 0, 1),
                    y: clamp(Number(((focal.y - frame.y) / frame.height).toFixed(4)), 0, 1),
                },
                scale_down: ui.scaleDown.checked,
            }]);
        }

        let stored = null;

        refuse(null, '');
        ui.status.textContent = steps.length > 0 ? texts.saving : '';

        for (const [what, method, body] of steps) {
            const answer = await send(url(what, file.id), method, body);

            if (! answer.ok || ! answer.data?.file) {
                ui.status.textContent = '';

                if (answer.status === 422) {
                    const refused = refusal(answer.data);

                    refuse(refused?.key === 'folder' ? 'folder' : (refused?.key ?? 'crop'), refused?.message ?? texts.save_failed);
                } else {
                    refuse('crop', answer.status === 401 || answer.status === 419 ? texts.expired : texts.save_failed);
                }

                if (stored) {
                    tell('made-cms:media-changed', { file: stored });
                }

                return false;
            }

            stored = answer.data.file;
            current.file = stored;
            base.name = stored.name;
            base.alt = stored.alt ?? '';

            if (what === 'move') {
                base.folder = folder.value;
            }

            if (what === 'crop') {
                Object.assign(base.crop, body.crop);
                base.scaleDown = body.scale_down;
                base.focal = { ...body.focal };
            }
        }

        ui.status.textContent = '';

        if (stored) {
            tell('made-cms:media-changed', { file: stored });
            announce(texts.saved);
        }

        return true;
    }

    /** "Opslaan" stores everything, "Zo laten" the fields on the right; then the next file, or the step closes. */
    async function save(withCrop) {
        if (busy || ! dialog.open) {
            return;
        }

        if (! working()) {
            next();

            return;
        }

        lock(true);

        const done = await store(withCrop);

        lock(false);

        if (done) {
            results[index] = { file: current.file, handled: true };
            next();
        } else {
            focusRefused();
        }
    }

    function next() {
        if (index + 1 < queue.length) {
            load(index + 1);
        } else {
            dialog.close();
        }
    }

    /** The cross and Escape: the fields on the right are stored, the photo stays as it is, the queue stops. */
    async function leave() {
        if (busy) {
            return;
        }

        if (working()) {
            lock(true);

            const done = await store(false);

            lock(false);

            if (! done) {
                focusRefused();

                return;
            }

            results[index] = { file: current.file, handled: true };
        }

        dialog.close();
    }

    // The editor's tools.

    function turn(clockwise) {
        const { width, height } = size();
        const { frame, focal } = view;

        // Turning right after mirroring is turning left before it: r' = flip ? r - 90 : r + 90.
        view.rotation = (view.rotation + (clockwise !== view.flip ? 90 : 270)) % 360;
        view.frame = clockwise
            ? { x: height - frame.y - frame.height, y: frame.x, width: frame.height, height: frame.width }
            : { x: frame.y, y: width - frame.x - frame.width, width: frame.height, height: frame.width };
        view.focal = clockwise
            ? { x: height - focal.y, y: focal.x }
            : { x: focal.y, y: width - focal.x };

        if (ratio !== 'free') {
            view.frame = fit(ratio, { x: view.frame.x + view.frame.width / 2, y: view.frame.y + view.frame.height / 2 });
        }

        keepFocal();
        draw();
        announce(areaText());
    }

    function mirror() {
        const { width } = size();

        view.flip = ! view.flip;
        view.frame.x = width - view.frame.x - view.frame.width;
        view.focal.x = width - view.focal.x;
        changed();
    }

    function choose(key) {
        ratio = key;

        if (key !== 'free') {
            view.frame = fit(key, { x: view.frame.x + view.frame.width / 2, y: view.frame.y + view.frame.height / 2 });
        }

        changed();
        announce(areaText());
    }

    function reset() {
        const original = current.details.original;

        view = {
            rotation: 0,
            flip: false,
            frame: { x: 0, y: 0, width: original.width, height: original.height },
            focal: { x: original.width / 2, y: original.height / 2 },
        };
        ratio = 'original';
        draw();
        announce(areaText());
    }

    function move(dx, dy) {
        const { width, height } = size();
        const { frame } = view;

        frame.x = clamp(Math.round(frame.x + dx), 0, width - frame.width);
        frame.y = clamp(Math.round(frame.y + dy), 0, height - frame.height);
        changed();
    }

    /**
     * Pulls a corner to a point of the view: the opposite corner stays, the frame keeps its ratio unless it is free,
     * never leaves the view and never gets smaller than the smallest frame.
     */
    function pull(corner, point) {
        const { width, height } = size();
        const { frame } = view;
        const left = corner.includes('w');
        const top = corner.includes('n');
        const anchor = { x: left ? frame.x + frame.width : frame.x, y: top ? frame.y + frame.height : frame.y };
        const room = { width: left ? anchor.x : width - anchor.x, height: top ? anchor.y : height - anchor.y };
        const least = smallest();
        const px = clamp(point.x, 0, width);
        const py = clamp(point.y, 0, height);
        const wanted = proportion(ratio);
        let w;
        let h;

        if (wanted === null) {
            w = clamp(Math.round(left ? anchor.x - px : px - anchor.x), Math.min(least, room.width), room.width);
            h = clamp(Math.round(top ? anchor.y - py : py - anchor.y), Math.min(least, room.height), room.height);
        } else {
            const most = Math.min(room.width, room.height * wanted);

            w = clamp(Math.max(left ? anchor.x - px : px - anchor.x, (top ? anchor.y - py : py - anchor.y) * wanted), Math.min(least, most), most);
            w = Math.round(w);
            h = Math.min(Math.round(w / wanted), room.height);
        }

        view.frame = { x: left ? anchor.x - w : anchor.x, y: top ? anchor.y - h : anchor.y, width: w, height: h };
        changed();
    }

    function place(point) {
        const { frame } = view;

        view.focal = {
            x: clamp(Math.round(point.x), frame.x, frame.x + frame.width),
            y: clamp(Math.round(point.y), frame.y, frame.y + frame.height),
        };
        drawFrame();
    }

    /** A point of the pointer in pixels of the view. */
    function pointOf(event) {
        const box = ui.picture.getBoundingClientRect();

        return { x: (event.clientX - box.left) / scale, y: (event.clientY - box.top) / scale };
    }

    /** Drags with a pointer from an element: the frame moves, a corner pulls, the focal point goes along. */
    function drag(element, onMove) {
        element.addEventListener('pointerdown', (event) => {
            if (busy || ! working() || event.button !== 0 || (element === ui.frame && event.target !== ui.frame)) {
                return;
            }

            event.preventDefault();
            element.setPointerCapture(event.pointerId);
            element.focus({ preventScroll: true });

            const start = pointOf(event);
            const frame = { ...view.frame };
            const follow = (moved) => onMove(pointOf(moved), start, frame);
            const end = () => {
                element.removeEventListener('pointermove', follow);
                element.removeEventListener('pointerup', end);
                element.removeEventListener('pointercancel', end);
            };

            element.addEventListener('pointermove', follow);
            element.addEventListener('pointerup', end);
            element.addEventListener('pointercancel', end);
        });
    }

    drag(ui.frame, (point, start, frame) => {
        view.frame.x = frame.x;
        view.frame.y = frame.y;
        move(point.x - start.x, point.y - start.y);
    });

    for (const corner of corners) {
        drag(corner, (point) => pull(corner.dataset.corner, point));
    }

    drag(ui.focal, (point) => place(point));

    /** The arrow keys: a share of the view, Shift a larger one. */
    function arrow(event) {
        const keys = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };

        if (! (event.key in keys) || busy || ! working() || ! current.picture) {
            return null;
        }

        event.preventDefault();
        event.stopPropagation();

        const [x, y] = keys[event.key];
        const share = event.shiftKey ? LARGE_STEP : STEP;

        return { x, y, share };
    }

    ui.frame.addEventListener('keydown', (event) => {
        if (event.target !== ui.frame) {
            return;
        }

        const key = arrow(event);

        if (key) {
            const { width, height } = size();

            move(key.x * Math.max(1, Math.round(width * key.share)), key.y * Math.max(1, Math.round(height * key.share)));
            readOut('area');
        }
    });

    for (const corner of corners) {
        corner.addEventListener('keydown', (event) => {
            const key = arrow(event);

            if (key) {
                const { width, height } = size();
                const { frame } = view;
                const name = corner.dataset.corner;
                const at = {
                    x: (name.includes('w') ? frame.x : frame.x + frame.width) + key.x * Math.max(1, Math.round(width * key.share)),
                    y: (name.includes('n') ? frame.y : frame.y + frame.height) + key.y * Math.max(1, Math.round(height * key.share)),
                };

                pull(name, at);
                readOut('area');
            }
        });
    }

    ui.focal.addEventListener('keydown', (event) => {
        const key = arrow(event);

        if (key) {
            const { frame, focal } = view;

            place({
                x: focal.x + key.x * Math.max(1, frame.width * key.share),
                y: focal.y + key.y * Math.max(1, frame.height * key.share),
            });
            readOut('focal');
        }
    });

    ratios.forEach((button) => button.addEventListener('click', () => choose(button.dataset.ratio)));
    ui.left.addEventListener('click', () => turn(false));
    ui.right.addEventListener('click', () => turn(true));
    ui.flip.addEventListener('click', mirror);
    ui.reset.addEventListener('click', reset);
    ui.scaleDown.addEventListener('change', drawWeight);
    ui.save.addEventListener('click', () => save(true));
    ui.keep.addEventListener('click', () => save(false));
    ui.close.addEventListener('click', leave);

    [ui.name, ui.alt].forEach((input) => input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            save(true);
        }
    }));

    // Escape stores the fields on the right, like the cross.
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        leave();
    });

    /** Tab goes round inside the dialog, never out to the browser. */
    dialog.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab') {
            return;
        }

        const focusable = [...dialog.querySelectorAll('button, input, select, textarea, a[href], [tabindex]')]
            .filter((element) => ! element.disabled && element.tabIndex >= 0 && element.checkVisibility());
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (! first) {
            return;
        }

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (! event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    new ResizeObserver(() => draw()).observe(ui.stage);

    dialog.addEventListener('made-cms:crop', (event) => {
        const detail = event.detail ?? {};
        const files = (detail.files ?? []).filter((file) => file?.croppable);

        // One step at a time: a second host gets its files back as not handled.
        if (dialog.open || files.length === 0) {
            detail.done?.((detail.files ?? []).map((file) => ({ file, handled: false })));

            return;
        }

        request = detail;
        queue = files;
        results = [];
        dialog.showModal();
        folders();
        load(0);
    });

    // However it closed, the host hears which files the editor handled, and the focus goes back where it came from.
    dialog.addEventListener('close', () => {
        const finished = request;
        const answered = queue.map((file, position) => results[position] ?? { file, handled: false });

        loaded++;
        clearTimeout(reading);
        current?.picture?.close?.();
        request = null;
        queue = [];
        results = [];
        current = null;
        view = null;
        lock(false);

        finished?.done?.(answered);

        if (finished?.opener?.isConnected) {
            finished.opener.focus();
        }
    });
}

document.querySelectorAll('[data-media-crop]').forEach(step);
