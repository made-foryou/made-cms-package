<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost']);

    $this->about = Page::factory()->published()->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('asks one question per step, follows the name in the address and creates the page', function (): void {
    $wizard = visit('/made-cms/pages/new');

    $wizard->assertVisible('[data-stap="1"]')
        ->assertMissing('[data-stap="2"]')
        ->assertMissing('[data-slug-veld]')
        ->assertSeeIn('[data-stapnoot]', 'Stap 1 van 2');

    $wizard->type('#made-cms-title', 'Jeugd & Café');

    expect($wizard->script("() => [...document.querySelectorAll('[data-slug-preview]')].map((preview) => preview.textContent)"))
        ->toBe(['jeugd-cafe', 'jeugd-cafe', 'jeugd-cafe']);

    $wizard->click('[data-next]')
        ->assertMissing('[data-stap="1"]')
        ->assertVisible('[data-stap="2"]')
        ->assertSeeIn('[data-stapnoot]', 'Stap 2 van 2')
        ->assertSeeIn('[data-title-echo]', 'Jeugd & Café');

    $wizard->click('Onder Over ons')
        ->click('Pagina aanmaken')
        ->assertSee('Jeugd & Café staat klaar, nog leeg en nog niet zichtbaar.')
        ->assertNoJavaScriptErrors();

    expect(Page::query()->with('route')->where('title', 'Jeugd & Café')->sole()->route?->path)->toBe('/over-ons/jeugd-cafe');
});

it('stays on the first step without a name', function (): void {
    $wizard = visit('/made-cms/pages/new');

    $wizard->click('[data-next]')
        ->assertVisible('[data-stap="1"]')
        ->assertMissing('[data-stap="2"]')
        ->assertNoJavaScriptErrors();
});

it('lets the editor change the address', function (): void {
    $wizard = visit('/made-cms/pages/new');

    $wizard->type('#made-cms-title', 'Jeugd')
        ->click('[data-slug-edit]')
        ->assertVisible('[data-slug-veld]')
        ->assertValue('#made-cms-slug', 'jeugd')
        ->clear('#made-cms-slug')
        ->type('#made-cms-slug', 'onze-jeugd');

    expect($wizard->script("() => document.querySelector('[data-slug-preview]').textContent"))->toBe('onze-jeugd');

    $wizard->click('[data-next]')->click('Pagina aanmaken')->assertSee('staat klaar');

    expect(Page::query()->with('route')->where('title', 'Jeugd')->sole()->route?->path)->toBe('/onze-jeugd');
});
