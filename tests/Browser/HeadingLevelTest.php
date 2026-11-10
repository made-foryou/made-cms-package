<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useWorkbenchSite();

    // The heading of the hero is at H1, not at the default level of the field (H2); the titles of the cards are at H3.
    $this->page = Page::factory()->published()->at('/')->withStrips([
        storedStrip('hero', ['kop' => ['text' => 'Welkom bij de club', 'tag' => 'h1']], id: 'hero'),
        storedStrip('cards', ['kop' => 'Wat we doen', 'kolommen' => '3', 'kaarten' => [
            ['id' => '7e3a4b5c-6d7e-4f8a-8b9c-1d2e3f4a5b6c', 'titel' => 'Trainen', 'tekst' => 'Elke dinsdag'],
            ['id' => '8f4b5c6d-7e8f-4a9b-9c0d-2e3f4a5b6c7d', 'titel' => 'Spelen', 'tekst' => 'Competitie'],
        ]], id: 'cards'),
    ])->create(['title' => 'Home']);
});

/**
 * A JavaScript expression for the element the site prints for the heading of the hero.
 */
function heroHeading(): string
{
    return siteInFrame()."?.querySelector('[data-cms-strip=\"hero\"] [data-cms-field=\"kop\"]')";
}

/**
 * A script that lists the levels of a heading in the side panel, the chosen one with a star.
 */
function levelsIn(string $path): string
{
    return <<<JS
        () => [...document.querySelectorAll('[data-path="{$path}"] [role="option"]')]
            .map((option) => option.querySelector('[data-slot="level-label"]').textContent + (option.getAttribute('aria-selected') === 'true' ? '*' : ''))
        JS;
}

it('lets the SEO specialist choose the level of a heading, after which the frame shows that element', function (): void {
    $this->actingAs(Editor::factory()->withRoles(Role::SeoSpecialist)->create(), 'made-cms');
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"kop\"] [data-slot=\"level\"]')?.textContent.trim() === 'H1'");
    $heading = heroHeading();

    $shell->assertAttribute('[data-path="kop"] [data-slot="level"]', 'aria-label', 'Niveau van de kop: H1. Wijzigen')
        ->assertAttribute('[data-path="kop"] [data-slot="level"]', 'aria-expanded', 'false')
        ->assertSeeIn('[data-path="kop"] [data-slot="level-hint"]', 'Het niveau is voor Google en schermlezers.')
        ->assertMissing('[data-path="kop"] [role="listbox"]')
        ->click('[data-path="kop"] [data-slot="level"]')
        ->assertVisible('[data-path="kop"] [role="listbox"]')
        ->assertAttribute('[data-path="kop"] [data-slot="level"]', 'aria-expanded', 'true')
        ->assertSeeIn('[data-path="kop"] [role="option"]:nth-child(3)', 'Een onderdeel binnen een strook, zoals een kaart.');

    expect($shell->script(levelsIn('kop')))->toBe(['H1*', 'H2', 'H3', 'H4', 'H5', 'H6', 'Geen kop']);

    $shell->script(markFrame());
    $shell->click('[data-path="kop"] [role="option"]:nth-child(3)');

    expect($shell->script(waitUntil(frameReloaded()." && {$heading}?.localName === 'h3' && {$heading}.textContent === 'Welkom bij de club'")))->toBeTrue()
        ->and(draftOf($this->page, 'hero')['values']['kop'] ?? null)->toBe(['text' => 'Welkom bij de club', 'tag' => 'h3']);

    $shell->assertSeeIn('[data-path="kop"] [data-slot="level"]', 'H3')
        ->assertAttribute('[data-path="kop"] [data-slot="level"]', 'aria-expanded', 'false')
        ->assertMissing('[data-path="kop"] [role="listbox"]');

    // Typing at the same level changes the text of the element on the site without reloading the frame.
    $shell->script(markFrame());
    $shell->type('[data-path="kop"] input', 'Samen tafeltennissen');

    expect($shell->script(waitUntil("{$heading}?.localName === 'h3' && {$heading}.textContent === 'Samen tafeltennissen'")))->toBeTrue()
        ->and($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and($shell->script(valueAfter(frameReloaded(), 500)))->toBeFalse()
        ->and(draftOf($this->page, 'hero')['values']['kop'] ?? null)->toBe(['text' => 'Samen tafeltennissen', 'tag' => 'h3']);

    $shell->assertNoJavaScriptErrors();
});

it('lets the SEO specialist choose the level with the keyboard, and closes the levels with Escape', function (): void {
    $this->actingAs(Editor::factory()->withRoles(Role::SeoSpecialist)->create(), 'made-cms');
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"kop\"] [data-slot=\"level\"]')?.textContent.trim() === 'H1'");
    $active = "document.getElementById(document.querySelector('[data-path=\"kop\"] [role=\"listbox\"]').getAttribute('aria-activedescendant'))?.querySelector('[data-slot=\"level-label\"]').textContent";

    $shell->keys('[data-path="kop"] [data-slot="level"]', 'Enter');

    expect($shell->script(waitUntil("{$active} === 'H1' && document.activeElement?.getAttribute('role') === 'listbox'")))->toBeTrue();

    $shell->keys('[data-path="kop"] [role="listbox"]', 'ArrowDown')
        ->keys('[data-path="kop"] [role="listbox"]', 'ArrowDown');

    expect($shell->script(waitUntil("{$active} === 'H3'")))->toBeTrue();

    $shell->keys('[data-path="kop"] [role="listbox"]', 'Enter');

    expect($shell->script(waitUntil(heroHeading()."?.localName === 'h3' && document.activeElement?.dataset.slot === 'level'")))->toBeTrue()
        ->and(draftOf($this->page, 'hero')['values']['kop']['tag'] ?? null)->toBe('h3');

    $shell->keys('[data-path="kop"] [data-slot="level"]', 'Enter')
        ->keys('[data-path="kop"] [role="listbox"]', 'End')
        ->keys('[data-path="kop"] [role="listbox"]', 'Escape');

    expect($shell->script(waitUntil("document.activeElement?.dataset.slot === 'level'")))->toBeTrue();

    $shell->assertMissing('[data-path="kop"] [role="listbox"]')
        ->assertSeeIn('[data-path="kop"] [data-slot="level"]', 'H3')
        ->assertNoJavaScriptErrors();
});

it('lets the SEO specialist choose the level of a heading in an item, for that item only', function (): void {
    $this->actingAs(Editor::factory()->withRoles(Role::SeoSpecialist)->create(), 'made-cms');
    $shell = openStrip($this->page, 'cards', "document.querySelectorAll('[data-path=\"kaarten\"] li').length === 2");
    $titles = siteInFrame()."?.querySelectorAll('[data-cms-strip=\"cards\"] li [data-cms-field=\"titel\"]')";

    $shell->click('[data-path="kaarten"] li:nth-child(2) [data-slot="edit"]')
        ->assertSeeIn('[data-path="kaarten.1.titel"] .kopveld-kop [data-slot="label"]', 'Titel')
        ->assertSeeIn('[data-path="kaarten.1.titel"] [data-slot="level"]', 'H3')
        ->assertSeeIn('[data-path="kaarten.1.titel"] [data-slot="level-hint"]', 'Geldt voor dit item.');

    $shell->script(markFrame());
    $shell->click('[data-path="kaarten.1.titel"] [data-slot="level"]')
        ->click('[data-path="kaarten.1.titel"] [role="option"]:nth-child(2)');

    expect($shell->script(waitUntil(frameReloaded()." && {$titles}?.[1]?.localName === 'h2' && {$titles}[0].localName === 'h3'")))->toBeTrue()
        ->and(array_column(draftOf($this->page, 'cards')['values']['kaarten'] ?? [], 'titel'))->toBe([
            ['text' => 'Trainen', 'tag' => 'h3'],
            ['text' => 'Spelen', 'tag' => 'h2'],
        ]);

    $shell->assertNoJavaScriptErrors();
});

it('shows a content manager the level as a label only, and keeps it while the text changes', function (): void {
    $this->actingAs(Editor::factory()->withRoles(Role::ContentManager)->create(), 'made-cms');
    $shell = openStrip($this->page, 'hero', "document.querySelector('[data-path=\"kop\"] [data-slot=\"level-fixed\"]')?.textContent === 'H1'");
    $heading = heroHeading();

    $shell->assertAttribute('[data-path="kop"] [data-slot="level-fixed"]', 'aria-label', 'Niveau van de kop: H1, ingesteld door de SEO-specialist')
        ->assertSeeIn('[data-path="kop"] [data-slot="level-hint"]', 'Het niveau (H1) stelt de SEO-specialist in; de tekst pas je gewoon aan.')
        ->assertMissing('[data-path="kop"] [aria-haspopup="listbox"]')
        ->assertMissing('[data-path="kop"] [role="listbox"]');

    $shell->type('[data-path="kop"] input', 'Samen tafeltennissen');

    expect($shell->script(waitUntil("{$heading}?.localName === 'h1' && {$heading}.textContent === 'Samen tafeltennissen'")))->toBeTrue()
        ->and($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and(draftOf($this->page, 'hero')['values']['kop'] ?? null)->toBe(['text' => 'Samen tafeltennissen', 'tag' => 'h1']);

    $shell->assertNoJavaScriptErrors();
});
