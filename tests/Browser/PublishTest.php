<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useWorkbenchSite();

    $this->live = [
        storedStrip('hero', ['kop' => 'Welkom bij de club', 'knoppen' => []], id: 'hero'),
        storedStrip('cards', ['kop' => 'Wat we doen', 'kaarten' => [['id' => 'f2c1d6a8-3b4e-4c5d-8e9f-0a1b2c3d4e5f', 'titel' => 'Trainen']]], id: 'cards'),
    ];

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * A JavaScript condition on the text of a slot of the bar.
 */
function barSays(string $slot, string $text): string
{
    return "document.querySelector('[data-slot=\"{$slot}\"]')?.textContent.trim() === ".json_encode($text, JSON_THROW_ON_ERROR);
}

/**
 * A JavaScript expression for the heading of the hero in the frame.
 */
function heroInFrame(): string
{
    return "document.querySelector('.bewerkframe').contentDocument?.querySelector('[data-cms-strip=\"hero\"] h2')?.textContent";
}

it('counts what the editor changes and puts it live with Publiceren', function (): void {
    $page = Page::factory()->published()->at('/')->withStrips($this->live)->create(['title' => 'Home']);

    $shell = openStrip($page, 'hero', "document.querySelector('[data-path=\"kop\"] input')?.value === 'Welkom bij de club'");

    $shell->assertSeeIn('[data-slot="bar-state"]', 'Geen wijzigingen');

    expect($shell->script("() => [document.querySelector('[data-slot=\"bar-publish\"]').disabled, document.querySelector('[data-slot=\"bar-discard\"]').hidden]"))->toBe([true, true]);

    $shell->type('[data-path="kop"] input', 'Samen tafeltennissen');

    expect($shell->script(waitUntil(savedAsDraft().' && '.barSays('bar-state', 'Concept · 1 wijziging'))))->toBeTrue()
        ->and($shell->script("() => [document.querySelector('[data-slot=\"bar-publish\"]').disabled, document.querySelector('[data-slot=\"bar-discard\"]').hidden]"))->toBe([false, false]);

    $shell->script("() => { document.querySelector('.bewerkframe').contentWindow.voorHerladen = true; }");
    $shell->click('[data-slot="bar-publish"]');

    expect($shell->script(waitUntil(barSays('bar-state', 'Gepubliceerd').' && '.barSays('bar-page', 'Live'))))->toBeTrue()
        ->and($shell->script(waitUntil("! document.querySelector('.bewerkframe').contentWindow.voorHerladen && ".heroInFrame()." === 'Samen tafeltennissen'")))->toBeTrue()
        ->and($shell->script("() => [document.querySelector('[data-slot=\"bar-publish\"]').disabled, document.querySelector('[data-slot=\"bar-discard\"]').hidden]"))->toBe([true, true]);

    $stored = Page::query()->findOrFail($page->id);

    expect($stored->strips[0]['values']['kop'] ?? null)->toBe('Samen tafeltennissen')
        ->and($stored->draft_strips)->toBeNull();

    $shell->assertNoJavaScriptErrors();
});

it('puts a page that was never published live', function (): void {
    $page = Page::factory()->at('/')->withDraft($this->live)->create(['title' => 'Home']);

    $shell = visit('/made-cms/pages/'.$page->id.'/edit');

    $shell->assertSeeIn('[data-slot="bar-page"]', 'Concept')
        ->assertSeeIn('[data-slot="bar-state"]', 'Concept · 2 wijzigingen')
        ->click('[data-slot="bar-publish"]');

    expect($shell->script(waitUntil(barSays('bar-page', 'Live'))))->toBeTrue()
        ->and(Page::query()->findOrFail($page->id)->status)->toBe(PageStatus::Published);

    $shell->assertNoJavaScriptErrors();
});

it('throws the draft away once the editor confirms', function (): void {
    $draft = [storedStrip('hero', ['kop' => 'Nog niet af', 'knoppen' => []], id: 'hero'), $this->live[1]];
    $page = Page::factory()->published()->at('/')->withStrips($this->live)->withDraft($draft)->create(['title' => 'Home']);

    $shell = visit('/made-cms/pages/'.$page->id.'/edit');

    expect($shell->script(waitUntil(heroInFrame()." === 'Nog niet af'")))->toBeTrue();

    $shell->assertSeeIn('[data-slot="bar-state"]', 'Concept · 1 wijziging')
        ->click('[data-slot="bar-discard"] summary')
        ->assertSeeIn('[data-slot="bar-discard-text"]', 'Je wijziging gaat weg. De live versie blijft zoals hij is.');

    $shell->script('() => { window.voorWeggooien = true; }');
    $shell->click('[data-slot="bar-discard-confirm"]');

    expect($shell->script(waitUntil('! window.voorWeggooien && '.barSays('bar-state', 'Geen wijzigingen'))))->toBeTrue()
        ->and($shell->script(waitUntil(heroInFrame()." === 'Welkom bij de club'")))->toBeTrue()
        ->and(Page::query()->findOrFail($page->id)->draft_strips)->toBeNull();

    $shell->assertNoJavaScriptErrors();
});

it('names the strip that stops the publication and shows its error at the field', function (): void {
    $draft = [storedStrip('hero', ['kop' => null, 'knoppen' => []], id: 'hero'), $this->live[1]];
    $page = Page::factory()->published()->at('/')->withStrips($this->live)->withDraft($draft)->create(['title' => 'Home']);

    $shell = visit('/made-cms/pages/'.$page->id.'/edit');

    expect($shell->script(waitUntil("document.querySelector('.bewerkframe').contentDocument?.querySelector('made-cms-outlines')?.shadowRoot?.querySelectorAll('.omlijning').length === 2")))->toBeTrue();

    $shell->click('[data-slot="bar-publish"]');

    expect($shell->script(waitUntil(barSays('bar-state', 'Niet gepubliceerd: Hero — Vul Kop in.'))))->toBeTrue()
        ->and($shell->script(waitUntil("document.querySelector('[data-path=\"kop\"] [data-slot=\"error\"]')?.textContent === 'Vul Kop in.'")))->toBeTrue();

    $shell->assertSeeIn('[data-slot="name"]', 'Hero');

    expect(Page::query()->findOrFail($page->id)->strips)->toBe($this->live);

    $shell->assertNoJavaScriptErrors();
});

it('shows the page with its draft and without the CMS in the preview', function (): void {
    $draft = [storedStrip('hero', ['kop' => 'Nog niet af', 'knoppen' => []], id: 'hero'), $this->live[1]];
    Page::factory()->published()->at('/')->withStrips($this->live)->withDraft($draft)->create(['title' => 'Home']);

    visit('/?made-cms=preview')
        ->assertSee('Nog niet af')
        ->assertDontSee('Welkom bij de club')
        ->assertScript("document.querySelector('made-cms-launcher') === null")
        ->assertNoJavaScriptErrors();
});
