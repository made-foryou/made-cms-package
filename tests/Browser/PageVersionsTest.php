<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\PageVersion;

beforeEach(function (): void {
    useWorkbenchSite();

    $this->versions = [];

    foreach (['Opgericht in 1969', 'Nieuwe zaal', 'Samen tafeltennissen', 'Welkom bij de club'] as $index => $heading) {
        $this->versions[$index + 1] = [storedStrip('hero', ['kop' => $heading, 'knoppen' => []], id: 'hero')];
    }

    $this->page = Page::factory()->published()->at('/')->withStrips($this->versions[4])->create(['title' => 'Home']);

    foreach ($this->versions as $number => $strips) {
        PageVersion::factory()->for($this->page)->number($number)->withStrips($strips)->create();
    }

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * A JavaScript expression for the heading of the hero in the frame of the edit shell.
 */
function versionHeadingInFrame(): string
{
    return "document.querySelector('.bewerkframe')?.contentDocument?.querySelector('[data-cms-strip=\"hero\"] h2')?.textContent";
}

/**
 * A JavaScript condition on the text of a slot of the bar of the edit shell.
 */
function versionBarSays(string $slot, string $text): string
{
    return "document.querySelector('[data-slot=\"{$slot}\"]')?.textContent.trim() === ".json_encode($text, JSON_THROW_ON_ERROR);
}

it('puts an older version back as the draft, shows it in the edit shell and publishes it as the next version', function (): void {
    $screen = visit('/made-cms/pages/'.$this->page->id);

    $screen->assertSeeIn('#made-cms-versies', 'Wat er eerder gebeurde')
        ->assertSeeIn('#made-cms-versies', 'nu live')
        ->click('[aria-label="Versie 3 terugzetten"]')
        ->assertSeeIn('#made-cms-versies .melding', 'Versie 3 staat klaar als concept.')
        ->click('#made-cms-versies .melding a');

    expect($screen->script(waitUntil(versionHeadingInFrame()." === 'Samen tafeltennissen'")))->toBeTrue()
        ->and($screen->script(waitUntil(versionBarSays('bar-state', 'Concept · 1 wijziging'))))->toBeTrue();

    $screen->click('[data-slot="bar-publish"]');

    expect($screen->script(waitUntil(versionBarSays('bar-state', 'Gepubliceerd'))))->toBeTrue();

    $newest = PageVersion::query()->ofPage($this->page)->newestFirst()->firstOrFail();

    expect($newest->number)->toBe(5)
        ->and($newest->strips)->toBeStoredAs($this->versions[3]);

    $screen->assertNoJavaScriptErrors();
});

it('asks first when a version would take the place of the draft', function (): void {
    $this->page->update(['draft_strips' => [storedStrip('hero', ['kop' => 'Nog niet af', 'knoppen' => []], id: 'hero')]]);

    $screen = visit('/made-cms/pages/'.$this->page->id);

    $screen->click('[aria-label="Versie 2 terugzetten"]')
        ->assertSeeIn('#made-cms-versies details[open]', 'Je concept gaat weg: wat nog niet live staat, ben je kwijt.')
        ->assertSeeIn('#made-cms-versies details[open]', 'Ja, zet versie 2 terug')
        ->click('#made-cms-versies details[open] button[type="submit"]')
        ->assertSeeIn('#made-cms-versies .melding', 'Versie 2 staat klaar als concept; het concept dat er stond is weg.');

    expect(Page::query()->findOrFail($this->page->id)->draft_strips)->toBeStoredAs($this->versions[2]);

    $screen->assertNoJavaScriptErrors();
});
