/*
 * Made CMS launcher: the MADE tile in the bottom-right corner, the login panel it opens and the edit bar after logging in.
 * The markup arrives server-rendered as a declarative shadow root, with one <template> per login step;
 * this script only adds behaviour. The server decides every next step; all copy comes from the templates.
 * Timings follow the motion spec of the design "Made CMS — Inloggen" (1.9).
 */

const INTRO_KEY = 'made-cms-launcher-intro';
const INTRO_OPEN_AFTER = 900;
const INTRO_CLOSE_AFTER = 4200;
const CLOSE_FALLBACK = 700;
const STEP_OUT_FALLBACK = 250;
const BUSY_AFTER = 250;
const DONE_CONTINUES_AFTER = 1800;
const CODE_COMPLETE_DELAY = 200;
const SESSION_POLL = 5000;
const BAR_HEIGHT = '56px';
const CHANNEL = 'made-cms-package';
const LATIN = 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD';

/* The step counter: bar 1–3, 4 means logged in. */
const STEPS = {
    email: 1,
    method: 2,
    password: 2,
    code: 3,
    two_factor: 3,
    recovery: 3,
    two_factor_offer: 3,
    two_factor_setup: 3,
    recovery_codes: 3,
    done: 4,
    continue: 4,
};

/* From these steps on the editor is logged in: closing the panel finishes the login instead of cancelling it. */
const LOGGED_IN_STEPS = new Set(['two_factor_offer', 'two_factor_setup', 'recovery_codes', 'done', 'continue']);

let fontsLoaded = false;

/* The server-rendered panel markup, for hosts whose page snapshots lose the shadow root (back/forward in Turbo, Livewire). */
let markup = null;

/* What the last server answer said about this browser, so a restored page snapshot never shows a stale view. */
let knownView = null;

/*
 * @font-face inside a shadow root is ignored by browsers, so the fonts are added to the document
 * under their own family names; they never collide with the fonts of the host site.
 */
function loadFonts({ fontHeading, fontText }) {
    if (fontsLoaded || typeof FontFace === 'undefined' || !document.fonts) {
        return;
    }

    fontsLoaded = true;

    const faces = [
        ['Made CMS Nunito', fontHeading, '700'],
        ['Made CMS Instrument Sans', fontText, '400 700'],
    ];

    for (const [family, url, weight] of faces) {
        if (!url) {
            continue;
        }

        const face = new FontFace(family, `url("${url}") format("woff2")`, {
            weight,
            style: 'normal',
            display: 'swap',
            unicodeRange: LATIN,
        });

        document.fonts.add(face);
        face.load().catch(() => {});
    }
}

/* Remembers the first visit; without storage the intro stays quiet rather than repeating itself. */
function isFirstVisit() {
    try {
        if (window.localStorage.getItem(INTRO_KEY)) {
            return false;
        }

        window.localStorage.setItem(INTRO_KEY, '1');

        return true;
    } catch {
        return false;
    }
}

function wait(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

/* Resolves when the element's own animations end, or after the fallback when there are none (reduced motion, hidden tab). */
function animationsDone(element, fallback) {
    const animations = element.getAnimations ? element.getAnimations().map((animation) => animation.finished) : [];

    return Promise.race([Promise.allSettled(animations), wait(fallback)]);
}

/*
 * Logging in renews the csrf token of the session the host site shares with the CMS.
 * Forms and scripts of the host page still hold the old one, so they get the new token too.
 */
function shareToken(token) {
    if (!token) {
        return;
    }

    document.querySelectorAll('meta[name="csrf-token"]').forEach((meta) => meta.setAttribute('content', token));
    document.querySelectorAll('input[name="_token"]').forEach((input) => {
        input.value = token;
    });
}

class ApiError extends Error {
    constructor(status, data = {}) {
        super(`Made CMS request failed with status ${status}`);
        this.status = status;
        this.data = data;
    }
}

/* Talks to the CMS routes. The csrf token never sits in the page: it comes from GET session and every answer renews it. */
class Api {
    constructor(endpoints) {
        this.endpoints = endpoints;
        this.token = null;
    }

    async session() {
        const response = await fetch(this.endpoints.session, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!response.ok) {
            throw new ApiError(response.status);
        }

        const data = await response.json();
        this.token = data.csrf_token;
        knownView = data.view;

        return data;
    }

    /* The edit mode asks which strips the ids on the page are; the answer is data only: id, type and label. */
    async strips(ids) {
        const query = new URLSearchParams();

        ids.forEach((id) => query.append('ids[]', String(id)));

        const response = await fetch(`${this.endpoints.strips}?${query}`, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!response.ok) {
            throw new ApiError(response.status);
        }

        return (await response.json()).strips;
    }

    async post(endpoint, body = {}, retried = false) {
        if (!this.token) {
            await this.session();
        }

        const response = await fetch(this.endpoints[endpoint], {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });

        let data = {};

        try {
            data = await response.json();
        } catch {
            // An empty or html answer (a proxy error page) is handled by its status below.
        }

        if (data && data.csrf_token) {
            this.token = data.csrf_token;
        }

        // The token expired with the session (419): fetch a fresh one and try once more.
        if (response.status === 419 && !retried) {
            await this.session();

            return this.post(endpoint, body, true);
        }

        if (!response.ok) {
            throw new ApiError(response.status, data);
        }

        return data;
    }
}

/* The code boxes: digits only, paste and one-time-code autofill spread over the boxes, backspace goes back. */
class Digits {
    constructor(fieldset, onComplete) {
        this.fieldset = fieldset;
        this.inputs = [...fieldset.querySelectorAll('.cijfer')];
        this.onComplete = onComplete;
        this.completed = false;

        this.inputs.forEach((input, index) => {
            input.addEventListener('input', () => this.typed(index));
            input.addEventListener('keydown', (event) => this.key(event, index));
            input.addEventListener('focus', () => input.select());
            input.addEventListener('paste', (event) => {
                event.preventDefault();
                this.spread(event.clipboardData?.getData('text') ?? '', index);
            });
        });
    }

    typed(index) {
        const input = this.inputs[index];
        const digits = input.value.replace(/\D/g, '');

        this.invalid(false);

        // Several digits at once: autofill of the whole code (iOS, Android) or a fast typist.
        if (digits.length > 1) {
            this.spread(digits, index);

            return;
        }

        input.value = digits;

        if (digits && index < this.inputs.length - 1) {
            this.inputs[index + 1].focus();
        }

        this.check();
    }

    spread(text, from) {
        const digits = text.replace(/\D/g, '').split('');

        for (let index = from; index < this.inputs.length && digits.length; index++) {
            this.inputs[index].value = digits.shift();
        }

        const next = this.inputs.find((input) => !input.value) ?? this.inputs[this.inputs.length - 1];
        next.focus();
        this.check();
    }

    key(event, index) {
        const input = this.inputs[index];

        if (event.key === 'Backspace' && !input.value && index > 0) {
            event.preventDefault();
            this.inputs[index - 1].value = '';
            this.inputs[index - 1].focus();
            this.completed = false;
        } else if (event.key === 'ArrowLeft' && index > 0) {
            event.preventDefault();
            this.inputs[index - 1].focus();
        } else if (event.key === 'ArrowRight' && index < this.inputs.length - 1) {
            event.preventDefault();
            this.inputs[index + 1].focus();
        } else if (event.key === 'Home') {
            event.preventDefault();
            this.inputs[0].focus();
        } else if (event.key === 'End') {
            event.preventDefault();
            this.inputs[this.inputs.length - 1].focus();
        }
    }

    value() {
        return this.inputs.map((input) => input.value).join('');
    }

    complete() {
        return this.inputs.every((input) => /^\d$/.test(input.value));
    }

    /* After the last digit the code submits itself, once, 200 ms later (motion spec). */
    check() {
        if (!this.complete()) {
            this.completed = false;

            return;
        }

        if (!this.completed) {
            this.completed = true;
            setTimeout(() => this.complete() && this.onComplete(), CODE_COMPLETE_DELAY);
        }
    }

    clear() {
        this.inputs.forEach((input) => {
            input.value = '';
        });
        this.completed = false;
        this.inputs[0].focus({ preventScroll: true });
    }

    invalid(on) {
        this.fieldset.classList.toggle('fout', on);
        this.inputs.forEach((input) => input.setAttribute('aria-invalid', on ? 'true' : 'false'));
    }
}

/*
 * The login steps inside the panel or on the login page. It knows nothing about the tile or the edit bar:
 * the launcher passes in what should happen once the editor is logged in and when the flow is finished.
 */
class LoginFlow {
    constructor(container, { api, texts, onLoggedIn, onFinish }) {
        this.container = container;
        this.api = api;
        this.texts = texts;
        this.onLoggedIn = onLoggedIn;
        this.onFinish = onFinish;
        this.surface = container.querySelector('[data-stapvlak]');
        this.counter = container.querySelector('.stappen');
        this.general = container.querySelector('[data-algemeen]');
        this.alert = container.querySelector('[data-melding]');
        this.status = container.querySelector('[data-status]');
        this.state = {};
        this.step = null;
        this.current = null;
        this.busy = false;
        this.timers = [];
    }

    get loggedIn() {
        return LOGGED_IN_STEPS.has(this.step);
    }

    start(step = 'email') {
        this.stop();
        this.state = {};
        this.surface.replaceChildren();
        this.current = null;
        this.mount(step, { animate: false });
    }

    stop() {
        this.timers.forEach(clearTimeout);
        this.timers = [];
        this.stopWatching();
    }

    /* Moves to a step: the current one leaves upwards (or downwards when going back), the next one comes in. */
    async go(step, { back = false } = {}) {
        this.timers.forEach(clearTimeout);
        this.timers = [];
        this.stopWatching();
        this.hideGeneral();

        const leaving = this.current;

        if (leaving) {
            leaving.classList.remove('stap-in', 'terug');
            leaving.classList.add('stap-uit');
            leaving.classList.toggle('terug', back);
            await animationsDone(leaving, STEP_OUT_FALLBACK);
            leaving.remove();
        }

        this.mount(step, { animate: true, back });
    }

    mount(step, { animate, back = false }) {
        const template = this.container.getRootNode().querySelector(`template[data-step="${step}"]`);
        const fragment = template.content.cloneNode(true);
        const element = fragment.firstElementChild;

        this.fill(element);
        this.surface.append(element);

        if (animate) {
            element.classList.add('stap-in');
            element.classList.toggle('terug', back);
        }

        this.step = step;
        this.current = element;
        this.count(STEPS[step]);
        this.wire(step, element);
        this.focus(step, element);
    }

    /* Live values only ever go in through textContent and value: never as html. */
    fill(element) {
        const { state } = this;

        element.querySelectorAll('[data-slot]').forEach((slot) => {
            slot.textContent = state[slot.dataset.slot] ?? '';
        });

        element.querySelectorAll('[data-slot-value]').forEach((input) => {
            input.value = state[input.dataset.slotValue] ?? '';
        });

        element.querySelectorAll('[data-if]').forEach((part) => {
            const negate = part.dataset.if.startsWith('!');
            const value = Boolean(state[part.dataset.if.replace('!', '')]);
            part.hidden = negate ? value : !value;
        });
    }

    count(current) {
        const bars = [...this.counter.querySelectorAll('.stapbalk')];
        const label = this.texts.steps[current];

        bars.forEach((bar, index) => {
            bar.classList.toggle('af', index + 1 < current || current === 4);
            bar.classList.toggle('huidig', index + 1 === current);
        });

        this.counter.setAttribute('aria-label', label);
        this.counter.querySelector('.staplabel').textContent = label;
    }

    focus(step, element) {
        const target = {
            email: 'input[type="email"]',
            method: element.querySelector('.keuze.actief') ? '.keuze.actief' : '.keuze',
            password: 'input[type="password"]',
            recovery: 'input[name="recovery_code"]',
            two_factor_offer: '[data-actie="setup"]',
            continue: '.keuze',
        }[step] ?? (element.querySelector('.cijfer') ? '.cijfer' : 'h2');

        const field = element.querySelector(target) ?? element.querySelector('h2');
        field.focus({ preventScroll: true });

        if (field.select && field.value) {
            field.select();
        }
    }

    wire(step, element) {
        element.querySelectorAll('[data-actie]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                this.action(button.dataset.actie, button);
            });
        });

        element.querySelectorAll('[data-methode]').forEach((choice) => {
            choice.classList.toggle('actief', choice.dataset.methode === this.state.preferred);
            choice.addEventListener('click', () => this.choose(choice.dataset.methode, choice));
        });

        const digits = element.querySelector('[data-cijfers]');
        this.digits = digits ? new Digits(digits, () => this.submit(element)) : null;

        if (element instanceof HTMLFormElement) {
            element.addEventListener('submit', (event) => {
                event.preventDefault();
                this.submit(element);
            });
        }

        // Typing again removes the error state of that field.
        element.querySelectorAll('.veld input').forEach((input) => {
            input.addEventListener('input', () => this.clearError(input.closest('.veld')));
        });

        if (step === 'two_factor_setup') {
            this.showSetup(element);
        }

        if (step === 'recovery_codes') {
            const list = element.querySelector('[data-codes]');
            list.replaceChildren(...(this.state.recovery_codes ?? []).map((code) => {
                const item = document.createElement('li');
                item.textContent = code;

                return item;
            }));
        }

        if (step === 'code') {
            this.watchForLogin();
        }

        if (step === 'done') {
            this.timers.push(setTimeout(() => this.onFinish(), DONE_CONTINUES_AFTER));
        }
    }

    async choose(method, choice) {
        if (method === 'password') {
            this.state.preferred = 'password';
            await this.go('password');

            return;
        }

        await this.requestCode(choice);
    }

    async action(name, button) {
        switch (name) {
            case 'email':
                return this.go('email', { back: true });
            case 'method':
                return this.go('method', { back: true });
            case 'code':
                return this.requestCode(button);
            case 'recovery':
                return this.go('recovery');
            case 'two_factor':
                return this.go('two_factor', { back: true });
            case 'setup':
                return this.call(button, 'setup', {}, ({ data }) => {
                    Object.assign(this.state, data);

                    return this.go('two_factor_setup');
                });
            case 'done':
                return this.go('done');
            case 'saved':
                this.state.secured = true;

                return this.go('done');
            case 'finish':
                return this.onFinish();
            case 'copy-secret':
                return this.copy(this.state.secret ?? '', button);
            case 'copy-codes':
                return this.copy((this.state.recovery_codes ?? []).join('\n'), button);
            case 'download-codes':
                return this.download();
            default:
                return undefined;
        }
    }

    requestCode(button) {
        return this.call(button, 'code', { return_to: window.location.pathname + window.location.search }, ({ data }) => {
            Object.assign(this.state, data);

            return this.go('code');
        });
    }

    submit(form) {
        const button = form.querySelector('button[type="submit"]');

        switch (form.dataset.form) {
            case 'email':
                return this.call(button, 'email', { email: form.elements.email.value }, ({ data }) => {
                    Object.assign(this.state, { name: null, preferred: null }, data);

                    return this.go('method');
                });
            case 'password':
                return this.call(button, 'password', {
                    password: form.elements.password.value,
                    remember: form.elements.remember.checked,
                }, (answer) => this.next(answer));
            case 'verify':
                return this.call(button, 'verify', { code: this.digits.value() }, (answer) => this.next(answer));
            case 'two_factor':
                return this.call(button, 'two_factor', { code: this.digits.value() }, (answer) => this.next(answer));
            case 'recovery':
                return this.call(button, 'two_factor', { recovery_code: form.elements.recovery_code.value }, (answer) => this.next(answer));
            case 'confirm':
                return this.call(button, 'confirm', { code: this.digits.value() }, ({ data }) => {
                    Object.assign(this.state, data);

                    return this.go('recovery_codes');
                });
            default:
                return undefined;
        }
    }

    /* The server answered with the next step; a logged-in answer carries the editor. */
    next({ step, data }) {
        Object.assign(this.state, data);

        if (LOGGED_IN_STEPS.has(step) && data.editor) {
            this.onLoggedIn(data.editor, this.api.token);
        }

        return this.go(step);
    }

    /* One request at a time. The step stays until the server answered: an animation never pretends the action is done. */
    async call(button, endpoint, body, then) {
        if (this.busy) {
            return;
        }

        this.busy = true;
        this.hideGeneral();

        const busy = setTimeout(() => {
            button?.setAttribute('aria-busy', 'true');
            this.status.textContent = this.texts.busy;
        }, BUSY_AFTER);

        try {
            const data = await this.api.post(endpoint, body);
            clearTimeout(busy);
            button?.removeAttribute('aria-busy');
            this.status.textContent = '';
            this.busy = false;
            await then(data);
        } catch (error) {
            clearTimeout(busy);
            button?.removeAttribute('aria-busy');
            this.status.textContent = '';
            this.busy = false;
            this.fail(error);
        }
    }

    fail(error) {
        if (!(error instanceof ApiError)) {
            this.showGeneral(this.texts.network);

            return;
        }

        if (error.status === 429) {
            this.showGeneral(error.data.message ?? this.texts.network);

            return;
        }

        if (error.status !== 422 || !error.data.errors) {
            this.showGeneral(this.texts.network);

            return;
        }

        const errors = error.data.errors;

        // The login in progress is gone (expired session, other tab): start again at the e-mail step.
        if (errors.email && this.step !== 'email') {
            this.go('email', { back: true }).then(() => this.showError('email', errors.email[0]));

            return;
        }

        for (const [field, messages] of Object.entries(errors)) {
            this.showError(field, messages[0]);
        }
    }

    showError(field, message) {
        const target = this.current.querySelector(`[data-fout="${field}"]`);

        if (!target) {
            this.showGeneral(message);

            return;
        }

        const holder = target.closest('.veld, .cijfers');

        target.textContent = message;
        target.hidden = false;
        holder.classList.add('fout');
        holder.querySelectorAll('input').forEach((input) => input.setAttribute('aria-invalid', 'true'));
        this.alert.textContent = message;

        holder.classList.remove('schud');
        void holder.offsetWidth;
        holder.classList.add('schud');
        holder.addEventListener('animationend', () => holder.classList.remove('schud'), { once: true });

        if (this.digits && holder.matches('.cijfers')) {
            this.digits.clear();
            this.digits.invalid(true);
        } else {
            holder.querySelector('input')?.focus({ preventScroll: true });
        }
    }

    clearError(holder) {
        if (!holder || !holder.classList.contains('fout')) {
            return;
        }

        holder.classList.remove('fout');
        holder.querySelector('.melding').hidden = true;
        holder.querySelectorAll('input').forEach((input) => input.setAttribute('aria-invalid', 'false'));
    }

    showGeneral(message) {
        this.general.textContent = message;
        this.general.hidden = false;
        this.alert.textContent = message;
    }

    hideGeneral() {
        this.general.hidden = true;
        this.general.textContent = '';
    }

    /* The QR code is trusted svg from the server; it is parsed as an svg document and imported, never set as html. */
    showSetup(element) {
        const holder = element.querySelector('[data-qr]');
        const svg = new DOMParser().parseFromString(this.state.qr ?? '', 'image/svg+xml').documentElement;

        if (svg && svg.nodeName.toLowerCase() === 'svg') {
            svg.setAttribute('aria-hidden', 'true');
            holder.replaceChildren(document.importNode(svg, true));
        }

        element.querySelector('[data-otpauth]').setAttribute('href', this.state.otpauth_url ?? '#');
        element.querySelector('[data-slot="secret"]').textContent = (this.state.secret ?? '').replace(/(.{4})/g, '$1 ').trim();
    }

    async copy(text, button) {
        try {
            await navigator.clipboard.writeText(text);
            const label = button.textContent;
            button.textContent = this.texts.copied;
            this.status.textContent = this.texts.copied;
            this.timers.push(setTimeout(() => {
                button.textContent = label;
            }, 2000));
        } catch {
            // Without clipboard access the codes stay selectable (user-select: all).
        }
    }

    download() {
        const text = `${this.texts.codes_file}\n\n${(this.state.recovery_codes ?? []).join('\n')}\n`;
        const link = document.createElement('a');

        link.href = URL.createObjectURL(new Blob([text], { type: 'text/plain' }));
        link.download = 'made-cms-herstelcodes.txt';
        link.click();
        setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    }

    /*
     * "Klik op de link in de mail — dan logt dit tabblad vanzelf in": the link logs in this browser in another tab.
     * This tab notices through the shared session: when told by the other tab, when it becomes visible again and every few seconds.
     */
    watchForLogin() {
        const check = () => this.checkSession();

        this.watching = {
            visible: () => document.visibilityState === 'visible' && check(),
            interval: setInterval(() => document.visibilityState === 'visible' && check(), SESSION_POLL),
        };

        document.addEventListener('visibilitychange', this.watching.visible);
        window.addEventListener('focus', this.watching.visible);
    }

    stopWatching() {
        if (!this.watching) {
            return;
        }

        clearInterval(this.watching.interval);
        document.removeEventListener('visibilitychange', this.watching.visible);
        window.removeEventListener('focus', this.watching.visible);
        this.watching = null;
    }

    async checkSession() {
        if (this.busy || this.loggedIn) {
            return;
        }

        try {
            const session = await this.api.session();

            if (session.authenticated && !this.loggedIn) {
                this.state.editor = session.editor;
                this.onLoggedIn(session.editor, this.api.token, { broadcast: false });
                await this.go('done');
            }
        } catch {
            // Offline for a moment: the next check tries again.
        }
    }
}

/* Tells other tabs of this site that the editor logged in or out. They always check with the server themselves. */
class CrossTab {
    constructor(onMessage) {
        this.onMessage = onMessage;

        if (typeof BroadcastChannel !== 'undefined') {
            this.channel = new BroadcastChannel(CHANNEL);
            this.channel.addEventListener('message', (event) => this.onMessage(event.data?.type));
        } else {
            window.addEventListener('storage', (event) => {
                if (event.key === CHANNEL && event.newValue) {
                    this.onMessage(JSON.parse(event.newValue).type);
                }
            });
        }
    }

    send(type) {
        if (this.channel) {
            this.channel.postMessage({ type });

            return;
        }

        try {
            window.localStorage.setItem(CHANNEL, JSON.stringify({ type, at: Date.now() }));
        } catch {
            // Without storage other tabs find out on their next check.
        }
    }
}

class MadeCmsLauncher extends HTMLElement {
    connectedCallback() {
        if (this.started) {
            return;
        }

        const root = this.shadowRoot ?? this.attachTemplate();

        if (!root) {
            return;
        }

        this.started = true;
        loadFonts(this.dataset);

        // The page behind the mail link only needs the fonts; its button is a plain form.
        if (this.dataset.view === 'page') {
            this.dataset.ready = '';

            return;
        }

        if (this.dataset.view === 'login') {
            this.startPage(root);

            return;
        }

        if (root.querySelector('template[data-step]')) {
            markup = root.innerHTML;
        }

        const config = JSON.parse(root.querySelector('[data-config]')?.textContent ?? '{}');

        this.timers = [];
        this.texts = config.texts;
        this.api = new Api(config.endpoints);
        this.tile = root.querySelector('.beheer');
        this.dialog = root.querySelector('dialog');
        this.bar = root.querySelector('.balk');
        this.flow = new LoginFlow(root.querySelector('.inhoud'), {
            api: this.api,
            texts: this.texts,
            onLoggedIn: (editor, token, options) => this.loggedIn(editor, token, options),
            onFinish: () => this.finish(),
        });
        this.crossTab = new CrossTab((type) => this.heard(type));

        // A restored snapshot (Turbo, bfcache) may show what this browser no longer is: the last answer wins.
        if (knownView && knownView !== this.dataset.view) {
            this.dataset.view = knownView;
        }

        // Escape closes the panel wherever the focus is, also when it left the dialog.
        this.onEscape = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                this.close();
            }
        };

        this.onPageShow = (event) => event.persisted && this.reconcile();

        this.tile.addEventListener('click', () => this.open());
        root.querySelector('.sluit').addEventListener('click', () => this.close());
        root.querySelector('.uitloggen').addEventListener('submit', (event) => this.logout(event));
        window.addEventListener('pageshow', this.onPageShow);
        this.listenToDialog();

        // Only now the tile may show: a tile without working script would be a button that does nothing.
        this.dataset.ready = '';

        if (this.dataset.view === 'edit_bar') {
            this.showBar({ animate: this.hasAttribute('data-arrived') });

            if (this.hasAttribute('data-arrived')) {
                this.crossTab.send('login');
            }
        } else {
            this.intro();
        }
    }

    /*
     * The login page: the same steps as the panel, without tile, dialog or edit bar.
     * Once logged in, "klaar" leads on to "verder waar je was" instead of the edit bar.
     */
    startPage(root) {
        const config = JSON.parse(root.querySelector('[data-config]')?.textContent ?? '{}');

        this.api = new Api(config.endpoints);
        this.crossTab = new CrossTab(() => {});
        this.flow = new LoginFlow(root.querySelector('.pagina-flow'), {
            api: this.api,
            texts: config.texts,
            onLoggedIn: (editor, token, { broadcast = true } = {}) => {
                shareToken(token);

                if (broadcast) {
                    this.crossTab.send('login');
                }
            },
            onFinish: () => this.flow.step !== 'continue' && this.flow.go('continue'),
        });

        this.dataset.ready = '';
        this.flow.start(this.dataset.start || 'email');

        // A fresh token for this browser, fetched while the editor types; failures are retried by the first request.
        this.api.session().catch(() => {});
    }

    /* Removing an open modal fires no close event, so undo the scroll lock and the Escape listener here. */
    disconnectedCallback() {
        this.timers?.forEach(clearTimeout);
        this.flow?.stop();
        this.stopEditing();

        if (this.onPageShow) {
            window.removeEventListener('pageshow', this.onPageShow);
        }

        if (this.dialog?.open || this.scroll) {
            this.reset();
        }

        this.unshiftPage();
    }

    /* Hosts that swap the body through DOMParser (Turbo, Livewire navigate) never parse declarative shadow roots. */
    attachTemplate() {
        const template = this.querySelector('template[shadowrootmode]');

        if (!template && markup === null) {
            return null;
        }

        const root = this.attachShadow({ mode: 'open', clonable: true });

        if (template) {
            root.append(template.content.cloneNode(true));
            template.remove();
        } else {
            root.innerHTML = markup;
        }

        return root;
    }

    intro() {
        if (this.dataset.view !== 'tile' || !isFirstVisit()) {
            return;
        }

        this.timers.push(
            setTimeout(() => this.tile.classList.add('intro'), INTRO_OPEN_AFTER),
            setTimeout(() => this.tile.classList.remove('intro'), INTRO_CLOSE_AFTER),
        );
    }

    listenToDialog() {
        const dialog = this.dialog;

        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            this.close();
        });

        // Typing an e-mail address must not trigger the host site's keyboard shortcuts.
        for (const type of ['keydown', 'keyup', 'keypress']) {
            dialog.addEventListener(type, (event) => event.stopPropagation());
        }

        // The panel content fills the whole dialog, so only a press on the backdrop targets the dialog itself.
        dialog.addEventListener('pointerdown', (event) => {
            this.pressedBackdrop = event.target === dialog;
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog && this.pressedBackdrop) {
                this.close();
            }

            this.pressedBackdrop = false;
        });

        dialog.addEventListener('close', () => this.closed());
    }

    open() {
        if (this.dialog.open) {
            return;
        }

        this.timers.forEach(clearTimeout);
        this.tile.classList.remove('intro');
        this.dialog.classList.remove('sluitend', 'naar-balk');
        this.lockScroll();
        document.addEventListener('keydown', this.onEscape, true);
        this.dialog.showModal();
        this.flow.start('email');

        // A fresh token for this browser, fetched while the editor types; failures are retried by the first request.
        this.api.session().catch(() => {});
    }

    /* Closing after the login went through finishes it; before that it cancels and the tile comes back. */
    async close() {
        if (this.flow.loggedIn) {
            return this.finish();
        }

        if (!this.dialog.open || this.closing) {
            return undefined;
        }

        this.closing = true;
        this.freezeGrowth();
        this.dialog.classList.add('sluitend');

        const animations = this.dialog.getAnimations({ subtree: true }).map((animation) => animation.finished);

        await Promise.race([
            Promise.allSettled(animations),
            wait(CLOSE_FALLBACK),
        ]);

        this.dialog.close();

        return undefined;
    }

    /* Closing while the panel still grows: freeze its current size, so it shrinks from there instead of jumping. */
    freezeGrowth() {
        for (const animation of this.dialog.getAnimations()) {
            if (animation.animationName === 'paneel-groei' && animation.playState === 'running') {
                animation.commitStyles();
                animation.cancel();
            }
        }
    }

    /* Runs after every close, also when the browser closes the dialog without asking. */
    closed() {
        const finishing = this.finishing;

        this.reset();

        if (!finishing && this.dataset.view === 'tile') {
            this.tile.focus({ preventScroll: true });
        }
    }

    reset() {
        this.closing = false;
        this.finishing = false;
        this.flow?.stop();
        document.removeEventListener('keydown', this.onEscape, true);
        this.dialog.classList.remove('sluitend', 'naar-balk');
        this.dialog.removeAttribute('style');
        this.unlockScroll();
    }

    loggedIn(editor, token, { broadcast = true } = {}) {
        this.editor = editor;
        knownView = 'edit_bar';
        this.shareToken(token);

        if (broadcast) {
            this.crossTab.send('login');
        }
    }

    /* "Klaar → bewerkmodus": the panel shrinks back to the tile, the edit bar slides in. Nothing loads, nothing navigates. */
    async finish() {
        if (this.finishing) {
            return;
        }

        this.finishing = true;
        this.flow.stop();

        if (this.dialog.open) {
            this.freezeGrowth();
            this.dialog.classList.add('naar-balk');
            await animationsDone(this.dialog, CLOSE_FALLBACK);
            this.dialog.close();
        }

        this.finishing = false;
        this.showBar({ animate: true });
    }

    showBar({ animate }) {
        const editor = this.editor ?? JSON.parse(this.shadowRoot.querySelector('[data-config]')?.textContent ?? '{}').editor;

        if (editor) {
            this.bar.querySelector('.avatar').textContent = editor.initial;
        }

        this.dataset.view = 'edit_bar';
        this.shiftPage();
        this.bar.classList.remove('uit');
        this.bar.classList.toggle('in', animate);

        if (animate) {
            this.bar.focus({ preventScroll: true });
        }

        this.startEditing({ animate });
    }

    /* The edit mode (1.7): outlines on the strips of the page while the bar shows. Loaded on first use, never for visitors. */
    async startEditing({ animate }) {
        if (!this.dataset.editScript) {
            return;
        }

        this.editing ??= import(this.dataset.editScript)
            .then(({ EditMode }) => new EditMode(this.shadowRoot, {
                describe: (ids) => this.api.strips(ids),
                onChange: (state) => this.showStrip(state),
            }))
            .catch(() => null);

        const editMode = await this.editing;

        // The bar may have gone again while the script loaded.
        if (editMode && this.dataset.view === 'edit_bar') {
            editMode.start({ animate });
        }
    }

    stopEditing() {
        this.editing?.then((editMode) => editMode?.stop());
    }

    /* The bar names the selected strip, or says how to select one; it says nothing on a page without strips. */
    showStrip({ selected, count }) {
        const holder = this.bar.querySelector('[data-strook]');

        holder.hidden = count === 0;
        holder.querySelector('[data-strook-hint]').hidden = selected !== null;
        holder.querySelector('[data-strook-gekozen]').hidden = selected === null;
        holder.querySelector('[data-strook-label]').textContent = selected?.label ?? '';
    }

    /* Logging out keeps the page: the bar slides away and the tile (if this visitor gets one) comes back. */
    async logout(event) {
        event.preventDefault();

        if (this.loggingOut) {
            return;
        }

        this.loggingOut = true;

        try {
            const data = await this.api.post('logout');
            this.shareToken(data.csrf_token);
            knownView = data.view;
            this.crossTab.send('logout');
            await this.hideBar(data.view);
        } catch {
            // Without an answer the form posts the old-fashioned way, with the newest token this tab knows.
            this.shareToken(this.api.token);
            event.target.submit();
        } finally {
            this.loggingOut = false;
        }
    }

    async hideBar(view) {
        this.stopEditing();
        this.bar.classList.remove('in');
        this.bar.classList.add('uit');
        await animationsDone(this.bar, CLOSE_FALLBACK);
        this.bar.classList.remove('uit');
        this.unshiftPage();
        this.editor = null;
        this.dataset.view = view;

        if (view === 'tile') {
            this.tile.classList.add('direct');
            this.tile.focus({ preventScroll: true });
        }
    }

    /* Another tab logged in or out: ask the server what this browser is now, never trust the message itself. */
    heard(type) {
        if (type === 'login' && this.dialog.open && this.flow.step === 'code') {
            this.flow.checkSession();

            return;
        }

        this.reconcile();
    }

    async reconcile() {
        try {
            const session = await this.api.session();

            if (session.view === this.dataset.view) {
                return;
            }

            if (session.view === 'edit_bar') {
                this.editor = session.editor;

                if (this.dialog.open) {
                    this.dialog.close();
                }

                this.showBar({ animate: true });
            } else if (this.dataset.view === 'edit_bar') {
                await this.hideBar(session.view);
            } else {
                this.dataset.view = session.view;
            }

            this.shareToken(session.csrf_token);
        } catch {
            // Offline: keep what is shown.
        }
    }

    /* The host page and the no-script logout form both need the token the session has now. */
    shareToken(token) {
        if (!token) {
            return;
        }

        shareToken(token);

        const form = this.shadowRoot.querySelector('.uitloggen');
        let input = form.querySelector('input[name="_token"]');

        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_token';
            form.prepend(input);
        }

        input.value = token;
    }

    /* The bar covers the top 56 px, so the page moves down underneath it; hosts with a fixed header can use the variable. */
    shiftPage() {
        const html = document.documentElement;

        if (this.shifted) {
            return;
        }

        this.shifted = { paddingTop: html.style.paddingTop, scrollPaddingTop: html.style.scrollPaddingTop };
        html.style.setProperty('--made-cms-bar-height', BAR_HEIGHT);
        html.style.paddingTop = `calc(${parseFloat(getComputedStyle(html).paddingTop) || 0}px + ${BAR_HEIGHT})`;
        html.style.scrollPaddingTop = BAR_HEIGHT;
    }

    unshiftPage() {
        if (!this.shifted) {
            return;
        }

        const html = document.documentElement;

        html.style.paddingTop = this.shifted.paddingTop;
        html.style.scrollPaddingTop = this.shifted.scrollPaddingTop;
        html.style.removeProperty('--made-cms-bar-height');
        this.shifted = null;
    }

    /* Hides the page scrollbar and pads its width, so the site does not shift and the backdrop still covers the full width. */
    lockScroll() {
        const html = document.documentElement;
        const scrollbar = window.innerWidth - html.clientWidth;

        this.scroll = { overflow: html.style.overflow, paddingRight: html.style.paddingRight };

        if (scrollbar > 0) {
            html.style.paddingRight = `${parseFloat(getComputedStyle(html).paddingRight) + scrollbar}px`;
        }

        html.style.overflow = 'hidden';
    }

    unlockScroll() {
        if (!this.scroll) {
            return;
        }

        const style = document.documentElement.style;

        style.overflow = this.scroll.overflow;
        style.paddingRight = this.scroll.paddingRight;
        this.scroll = null;
    }
}

if (!customElements.get('made-cms-launcher')) {
    customElements.define('made-cms-launcher', MadeCmsLauncher);
}
