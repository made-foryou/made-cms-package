<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useWorkbenchSite();

    $this->page = Page::factory()->published()->at('/')->withStrips([
        storedStrip('hero', ['kop' => 'Welkom bij de club'], id: 'hero'),
        storedStrip('cards', ['kop' => 'Wat we doen', 'kaarten' => [['titel' => 'Trainen']]], id: 'cards'),
    ])->create(['title' => 'Home']);

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('opens the edit shell with the strip chosen whose "Bewerken" was clicked, once', function (): void {
    $screen = visit('/made-cms/pages/'.$this->page->id);

    $screen->assertSeeIn('#made-cms-inhoud', 'Wat erop staat')
        ->assertSee('Welkom bij de club')
        ->click('[aria-label="Kaarten bewerken op de site"]');

    expect($screen->script(waitUntil("document.querySelector('[data-slot=\"head-name\"]')?.textContent.trim() === 'Kaarten'")))->toBeTrue();

    $screen->assertSeeIn('[data-slot="head-position"]', 'Strook 2 van 2')
        ->assertNoJavaScriptErrors();

    // The address drops the strip, so a reload opens the shell as always.
    expect($screen->script('() => window.location.search'))->toBe('');
});
