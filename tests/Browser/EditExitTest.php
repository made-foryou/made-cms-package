<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost']);
    Storage::fake('public');
    Route::get('storage/{path}', fn (string $path) => response()->file(Storage::disk('public')->path($path)))->where('path', '.*');
    // An address that answers without a page, so leaving for it does not navigate: the bar must not wait for ever.
    Route::get('stil-204', fn () => response('', 204));
    useWorkbenchSite();

    $this->live = [
        storedStrip('hero', ['kop' => 'Onze teams'], id: 'hero'),
        storedStrip('cards', ['kop' => 'Wat we doen', 'kaarten' => [['id' => 'f2c1d6a8-3b4e-4c5d-8e9f-0a1b2c3d4e5f', 'titel' => 'Trainen']]], id: 'cards'),
    ];
    $this->page = Page::factory()->published()->at('/teams')->withStrips($this->live)->create(['title' => 'Teams']);

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * Opens the edit shell and waits until the bar has its script, so a click leaves through it and not through the link.
 */
function openExitShell(Page $page): mixed
{
    $shell = visit('/made-cms/pages/'.$page->id.'/edit');

    expect($shell->script(waitUntil("document.querySelector('[data-slot=\"bar-stop\"]')?.getAttribute('aria-haspopup') === 'dialog'")))->toBeTrue();

    return $shell;
}

/** A JavaScript condition: the choice under "Bewerken stoppen" is open. */
function choiceIsOpen(): string
{
    return "! document.querySelector('[data-slot=\"bar-stop-choice\"]').hidden";
}

/** A JavaScript condition: a slot of the bar says this. */
function barSlotSays(string $slot, string $text): string
{
    return "document.querySelector('[data-slot=\"{$slot}\"]')?.textContent.trim() === ".json_encode($text, JSON_THROW_ON_ERROR);
}

/** A script that holds the next upload on its way: window.release() sends it, window.drop() lets its connection drop. */
function holdPhotoUpload(): string
{
    return <<<'JS'
        () => {
            const send = XMLHttpRequest.prototype.send;

            XMLHttpRequest.prototype.send = function (...body) {
                XMLHttpRequest.prototype.send = send;
                window.release = () => send.apply(this, body);
                window.drop = () => this.dispatchEvent(new ProgressEvent('error'));
            };
        }
        JS;
}

/** A script that lets every save of a strip be refused the way the server refuses an invalid value. */
function refuseSaves(): string
{
    return <<<'JS'
        () => {
            const fetched = window.fetch;

            window.fetch = (url, options) => options?.method === 'PUT' && /\/strips\/[^/]+$/.test(String(url))
                ? Promise.resolve(new Response(JSON.stringify({ errors: { label: ['Een label mag hoogstens 60 tekens zijn.'] } }), { status: 422, headers: { 'Content-Type': 'application/json' } }))
                : fetched(url, options);
        }
        JS;
}

/** A script that lets every save of a strip fail the way the given answer says: a status, or null for no connection. */
function failSaves(?int $status): string
{
    $answer = $status === null
        ? "Promise.reject(new TypeError('offline'))"
        : "Promise.resolve(new Response('{}', { status: {$status}, headers: { 'Content-Type': 'application/json' } }))";

    return <<<JS
        () => {
            const fetched = window.fetch;

            window.failing = true;
            window.fetch = (url, options) => window.failing && options?.method === 'PUT' && /\\/strips\\/[^/]+$/.test(String(url))
                ? {$answer}
                : fetched(url, options);
        }
        JS;
}

/** A stored strip with a change in it: the heading of the hero. */
function withHeroChange(Page $page, string $heading): void
{
    $page->forceFill(['draft_strips' => [storedStrip('hero', ['kop' => $heading], id: 'hero'), $page->strips[1]]])->save();
}

it('leaves for the page as visitors see it, without the edit mode and with the bar of the site', function (): void {
    $shell = openExitShell($this->page);

    expect($shell->script("() => document.querySelector('[data-slot=\"bar-stop\"]').getAttribute('href')"))->toBe('/teams');

    $shell->click('[data-slot="bar-stop"]')
        ->assertPathIs('/teams')
        ->assertQueryStringMissing('made-cms');

    expect($shell->script(waitUntil("document.querySelector('made-cms-launcher')?.dataset.view === 'edit_bar'")))->toBeTrue()
        ->and($shell->script("() => document.querySelector('made-cms-outlines') === null && document.querySelector('[data-edit-shell]') === null"))->toBeTrue();
});

it('stores what is typed before it asks what to do with the draft', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"kop\"] input') !== null");

    $shell->type('[data-path="kop"] input', 'Al onze teams');
    $shell->click('[data-slot="bar-stop"]');

    expect($shell->script(waitUntil(choiceIsOpen())))->toBeTrue()
        ->and(draftOf($this->page, 'hero')['values']['kop'] ?? null)->toBe(['text' => 'Al onze teams', 'tag' => 'h2'])
        ->and($shell->script('() => location.pathname'))->toBe('/made-cms/pages/'.$this->page->id.'/edit');

    $shell->assertSeeIn('[data-slot="bar-stop-title"]', 'Je wijzigingen staan nog niet op de site')
        ->assertSeeIn('[data-slot="bar-stop-text-choice"]', 'Bezoekers zien je wijziging pas na Publiceren. Je concept blijft bewaard.');
});

it('puts the focus on stopping without publishing and lets Escape go on editing', function (): void {
    withHeroChange($this->page, 'Al onze teams');

    $shell = openExitShell($this->page);
    $shell->click('[data-slot="bar-stop"]');

    expect($shell->script(waitUntil(choiceIsOpen())))->toBeTrue()
        ->and($shell->script('() => document.activeElement?.dataset.slot'))->toBe('bar-stop-leave');

    $shell->keys('[data-slot="bar-stop-leave"]', 'Escape');

    expect($shell->script(waitUntil('! '.choiceIsOpen())))->toBeTrue()
        ->and($shell->script('() => document.activeElement?.dataset.slot'))->toBe('bar-stop');

    $shell->assertPathIs('/made-cms/pages/'.$this->page->id.'/edit');
});

it('keeps the draft when the editor stops without publishing', function (): void {
    withHeroChange($this->page, 'Al onze teams');

    $shell = openExitShell($this->page);
    $shell->click('[data-slot="bar-stop"]');

    expect($shell->script(waitUntil(choiceIsOpen())))->toBeTrue();

    $shell->click('[data-slot="bar-stop-leave"]')->assertPathIs('/teams');

    $page = Page::query()->findOrFail($this->page->id);

    expect($page->strips)->toBe($this->live)
        ->and($page->draft_strips)->not->toBeNull();
});

it('publishes the draft from the choice and lands on the site', function (): void {
    withHeroChange($this->page, 'Al onze teams');

    $shell = openExitShell($this->page);
    $shell->click('[data-slot="bar-stop"]');

    expect($shell->script(waitUntil(choiceIsOpen())))->toBeTrue();

    $shell->click('[data-slot="bar-stop-publish"]')->assertPathIs('/teams');

    $page = Page::query()->findOrFail($this->page->id);

    expect($page->draft_strips)->toBeNull()
        ->and($page->strips[0]['values']['kop'] ?? null)->toBe('Al onze teams');
});

it('stays when a page that cannot be published is published from the choice, and names the strip', function (): void {
    $this->page->forceFill(['draft_strips' => [storedStrip('hero', ['kop' => null], id: 'hero'), $this->live[1]]])->save();

    $shell = openExitShell($this->page);
    $shell->click('[data-slot="bar-stop"]');

    expect($shell->script(waitUntil(choiceIsOpen())))->toBeTrue();

    $shell->click('[data-slot="bar-stop-publish"]');

    expect($shell->script(waitUntil(barSlotSays('bar-notice', 'Niet gepubliceerd: Hero — Vul Kop in.'))))->toBeTrue()
        ->and($shell->script(waitUntil('! '.choiceIsOpen())))->toBeTrue()
        ->and($shell->script("() => document.querySelector('[data-slot=\"bar-notice\"]').hidden"))->toBeFalse();

    $shell->assertSeeIn('[data-slot="head-name"]', 'Hero')
        ->assertPathIs('/made-cms/pages/'.$this->page->id.'/edit');
});

it('stays and names the strip when what the panel holds cannot be stored', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"label\"] input') !== null");

    $shell->script(refuseSaves());
    $shell->type('[data-path="label"] input', 'Een veel te lang label');
    $shell->click('[data-slot="bar-stop"]');

    expect($shell->script(waitUntil(barSlotSays('bar-notice', 'Niet opgeslagen: Hero — Een label mag hoogstens 60 tekens zijn.'))))->toBeTrue()
        ->and($shell->script(waitUntil(barSlotSays('bar-state', 'Niet opgeslagen'))))->toBeTrue()
        ->and($shell->script("() => document.querySelector('[data-slot=\"bar-notice\"]').hidden"))->toBeFalse()
        ->and($shell->script('() => location.pathname'))->toBe('/made-cms/pages/'.$this->page->id.'/edit');

    $shell->assertSeeIn('[data-slot="head-name"]', 'Hero')
        ->assertSeeIn('[data-slot="bar-stop-text"]', 'Bewerken stoppen');
});

it('goes to the pages screen without a question when a page was never published and has no draft', function (): void {
    $page = Page::factory()->at('/concept')->create(['title' => 'Concept']);

    $shell = openExitShell($page);

    expect($shell->script("() => document.querySelector('[data-slot=\"bar-stop\"]').getAttribute('href')"))->toBe('/made-cms/pages/'.$page->id);

    $shell->click('[data-slot="bar-stop"]')->assertPathIs('/made-cms/pages/'.$page->id);
});

it('asks and goes to the pages screen when a page that was never published has a draft, and to the site after publishing', function (): void {
    $page = Page::factory()->at('/concept')->withDraft([storedStrip('hero', ['kop' => 'Nieuw'], id: 'hero')])->create(['title' => 'Concept']);

    $shell = openExitShell($page);
    $shell->click('[data-slot="bar-stop"]');

    expect($shell->script(waitUntil(choiceIsOpen())))->toBeTrue();

    $shell->assertSeeIn('[data-slot="bar-stop-title"]', 'Deze pagina staat nog niet op de site')
        ->assertSeeIn('[data-slot="bar-stop-text-choice"]', 'Je gaat terug naar Pagina’s.')
        ->click('[data-slot="bar-stop-leave"]')
        ->assertPathIs('/made-cms/pages/'.$page->id);

    expect(Page::query()->findOrFail($page->id)->status)->toBe(PageStatus::Draft);

    $shell = openExitShell($page);
    $shell->click('[data-slot="bar-stop"]');

    expect($shell->script(waitUntil(choiceIsOpen())))->toBeTrue();

    $shell->click('[data-slot="bar-stop-publish"]')->assertPathIs('/concept');

    expect(Page::query()->findOrFail($page->id)->status)->toBe(PageStatus::Published);
});

it('goes back to the page in the pages screen without a question, also with a draft, after storing what is typed', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"kop\"] input') !== null");

    $shell->type('[data-path="kop"] input', 'Al onze teams');
    $shell->click('[data-slot="bar-pages"]')->assertPathIs('/made-cms/pages/'.$this->page->id);

    expect(draftOf($this->page, 'hero')['values']['kop'] ?? null)->toBe(['text' => 'Al onze teams', 'tag' => 'h2']);
});

it('waits for a photo that is still uploading and lets the editor stay', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"foto\"] [data-slot=\"image-upload\"]') !== null");

    $shell->script(holdPhotoUpload());
    $shell->click('[data-path="foto"] [data-slot="image-upload"]')
        ->attach('[data-path="foto"] [data-slot="upload-input"]', photo(1200, 800, 'jpg', 'Clubavond.jpg'));

    expect($shell->script(waitUntil("typeof window.release === 'function'")))->toBeTrue();

    $shell->click('[data-slot="bar-stop"]');

    expect($shell->script(waitUntil(barSlotSays('bar-stop-text', 'Foto uploadt…'))))->toBeTrue()
        ->and($shell->script(waitUntil(barSlotSays('bar-state', 'Foto uploadt nog'))))->toBeTrue()
        ->and($shell->script("() => document.querySelector('[data-slot=\"bar-stay\"]').hidden"))->toBeFalse()
        ->and($shell->script("() => document.querySelector('[data-slot=\"bar-stop\"]').getAttribute('aria-busy')"))->toBe('true');

    $shell->click('[data-slot="bar-stay"]');

    expect($shell->script(waitUntil(barSlotSays('bar-stop-text', 'Bewerken stoppen'))))->toBeTrue()
        ->and($shell->script("() => document.querySelector('[data-slot=\"bar-stay\"]').hidden"))->toBeTrue();

    $shell->script('() => window.release()');

    // The photo lands either way, and the editor was not taken away.
    expect($shell->script(waitUntil("document.querySelector('[data-path=\"foto\"] [data-slot=\"image-card\"]')?.dataset.state === 'filled'")))->toBeTrue();

    $shell->assertPathIs('/made-cms/pages/'.$this->page->id.'/edit');
});

it('goes on to ask once the photo has landed', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"foto\"] [data-slot=\"image-upload\"]') !== null");

    $shell->script(holdPhotoUpload());
    $shell->click('[data-path="foto"] [data-slot="image-upload"]')
        ->attach('[data-path="foto"] [data-slot="upload-input"]', photo(1200, 800, 'jpg', 'Clubavond.jpg'));

    expect($shell->script(waitUntil("typeof window.release === 'function'")))->toBeTrue();

    $shell->click('[data-slot="bar-stop"]');

    expect($shell->script(waitUntil(barSlotSays('bar-stop-text', 'Foto uploadt…'))))->toBeTrue();

    $shell->script('() => window.release()');

    expect($shell->script(waitUntil(choiceIsOpen())))->toBeTrue()
        ->and(draftOf($this->page, 'hero')['values']['foto'] ?? null)->not->toBeNull();
});

it('reaches both ways out with the keyboard', function (): void {
    $shell = openExitShell($this->page);

    $shell->script("() => document.querySelector('[data-slot=\"bar-stop\"]').focus()");
    $shell->keys('[data-slot="bar-stop"]', 'Enter')->assertPathIs('/teams');

    $shell = openExitShell($this->page);

    $shell->script("() => document.querySelector('[data-slot=\"bar-pages\"]').focus()");
    $shell->keys('[data-slot="bar-pages"]', 'Enter')->assertPathIs('/made-cms/pages/'.$this->page->id);
});

it('fits a bar of 1024 pixels with short texts', function (): void {
    withHeroChange($this->page, 'Al onze teams');

    $shell = visit('/made-cms/pages/'.$this->page->id.'/edit')->resize(1024, 800);

    expect($shell->script(waitUntil(barSlotSays('bar-state', '1 wijziging'))))->toBeTrue()
        ->and($shell->script("() => getComputedStyle(document.querySelector('.bewerkbalk-site')).display"))->toBe('none')
        ->and($shell->script("() => document.querySelector('.bewerkbalk').getBoundingClientRect().height"))->toBe(56)
        ->and($shell->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue()
        ->and($shell->script("() => [...document.querySelectorAll('.bewerkbalk-stop, .bewerkbalk-knop, .bewerkbalk-terug')].every((el) => el.checkVisibility() && el.getBoundingClientRect().right <= window.innerWidth)"))->toBeTrue();

    $shell->assertSeeIn('.bewerkbalk-stop', 'Bewerken stoppen')
        ->assertSeeIn('.bewerkbalk-terug', 'Pagina’s');
});

it('shows the same bar in the dark theme', function (): void {
    withHeroChange($this->page, 'Al onze teams');

    $shell = visit('/made-cms/pages/'.$this->page->id.'/edit')->inDarkMode();

    $shell->assertSeeIn('.bewerkbalk-stop', 'Bewerken stoppen')
        ->assertNoJavaScriptErrors();
});

it('does not leave when a photo that was still uploading does not arrive', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"foto\"] [data-slot=\"image-upload\"]') !== null");

    $shell->script(holdPhotoUpload());
    $shell->click('[data-path="foto"] [data-slot="image-upload"]')
        ->attach('[data-path="foto"] [data-slot="upload-input"]', photo(1200, 800, 'jpg', 'Clubavond.jpg'));

    expect($shell->script(waitUntil("typeof window.drop === 'function'")))->toBeTrue();

    $shell->click('[data-slot="bar-stop"]');

    expect($shell->script(waitUntil(barSlotSays('bar-stop-text', 'Foto uploadt…'))))->toBeTrue();

    $shell->script('() => window.drop()');

    expect($shell->script(waitUntil(barSlotSays('bar-notice', 'Niet opgeslagen: Hero — De foto is niet geüpload.'))))->toBeTrue()
        ->and($shell->script(waitUntil(barSlotSays('bar-stop-text', 'Bewerken stoppen'))))->toBeTrue()
        ->and($shell->script('() => location.pathname'))->toBe('/made-cms/pages/'.$this->page->id.'/edit');

    $shell->assertSeeIn('[data-slot="head-name"]', 'Hero');
});

it('tries a save again that could not be reached, when the editor leaves again', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"label\"] input') !== null");

    $shell->script(failSaves(null));
    $shell->type('[data-path="label"] input', 'Nieuw label');
    $shell->click('[data-slot="bar-stop"]');

    expect($shell->script(waitUntil(barSlotSays('bar-notice', 'Dat lukte niet. Controleer je verbinding en probeer het opnieuw.'))))->toBeTrue()
        ->and($shell->script(waitUntil(barSlotSays('bar-stop-text', 'Bewerken stoppen'))))->toBeTrue();

    $shell->script('() => { window.failing = false; }');
    $shell->click('[data-slot="bar-stop"]');

    expect($shell->script(waitUntil(choiceIsOpen())))->toBeTrue()
        ->and(draftOf($this->page, 'hero')['values']['label'] ?? null)->toBe('Nieuw label')
        ->and($shell->script("() => document.querySelector('[data-slot=\"bar-notice\"]').hidden"))->toBeTrue();
});

it('says a session that has gone as such', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"label\"] input') !== null");

    $shell->script(failSaves(401));
    $shell->type('[data-path="label"] input', 'Nieuw label');
    $shell->click('[data-slot="bar-stop"]');

    expect($shell->script(waitUntil(barSlotSays('bar-notice', 'Je sessie is verlopen. Herlaad de pagina om verder te gaan.'))))->toBeTrue();
});

it('stores the alt text typed for a photo before it goes back to the pages screen', function (): void {
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"foto\"] [data-slot=\"image-upload\"]') !== null");

    $shell->click('[data-path="foto"] [data-slot="image-upload"]')
        ->attach('[data-path="foto"] [data-slot="upload-input"]', photo(1200, 800, 'jpg', 'Clubavond.jpg'));

    expect($shell->script(waitUntil("document.querySelector('[data-path=\"foto\"] [data-slot=\"image-card\"]')?.dataset.state === 'filled'")))->toBeTrue();

    // Typed, not saved with Enter or "Alt-tekst opslaan".
    $shell->type('[data-path="foto"] [data-slot="upload-row-alt-input"]', 'Het eerste team voor de tafels');
    $shell->click('[data-slot="bar-pages"]')->assertPathIs('/made-cms/pages/'.$this->page->id);

    expect(Media::query()->sole()->alt)->toBe('Het eerste team voor de tafels');
});

it('does not wait for ever when the panel cannot even try', function (): void {
    $shell = openExitShell($this->page);

    $shell->script("() => document.querySelector('[data-edit-shell]').addEventListener('made-cms:flush', (event) => event.detail.wait(Promise.reject(new Error('boom'))))");
    $shell->click('[data-slot="bar-stop"]');

    expect($shell->script(waitUntil(barSlotSays('bar-notice', 'Dat lukte niet. Controleer je verbinding en probeer het opnieuw.'))))->toBeTrue()
        ->and($shell->script(waitUntil(barSlotSays('bar-stop-text', 'Bewerken stoppen'))))->toBeTrue()
        ->and($shell->script("() => document.querySelector('.bewerkbalk').dataset.waiting === undefined"))->toBeTrue();
});

it('rests again when leaving does not navigate', function (): void {
    $page = Page::factory()->published()->at('/stil-204')->withStrips($this->live)->create(['title' => 'Stil']);

    $shell = openExitShell($page);
    $shell->click('[data-slot="bar-stop"]');

    expect($shell->script(waitUntil("document.querySelector('.bewerkbalk').dataset.waiting === 'stop'")))->toBeTrue();

    // The bar waits for a navigation for ten seconds before it rests again.
    $shell->script('() => new Promise((resolve) => setTimeout(resolve, 10600))');

    expect($shell->script("() => document.querySelector('.bewerkbalk').dataset.waiting === undefined"))->toBeTrue()
        ->and($shell->script("() => document.querySelector('[data-slot=\"bar-stop-text\"]').textContent.trim()"))->toBe('Bewerken stoppen')
        ->and($shell->script("() => document.querySelector('[data-slot=\"bar-stop\"]').hasAttribute('aria-busy')"))->toBeFalse();
});
