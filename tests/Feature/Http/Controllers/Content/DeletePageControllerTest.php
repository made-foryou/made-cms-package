<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('deletes the page and offers to restore it', function (): void {
    $page = Page::factory()->at('/over-ons')->create(['title' => 'Over ons']);

    $this->delete('made-cms/pages/'.$page->id)->assertRedirect('/made-cms/pages');

    expect(Page::query()->find($page->id))->toBeNull();

    $this->get('made-cms/pages')
        ->assertSee('Over ons is verwijderd.')
        ->assertSee('<div class="bericht" role="status">', false)
        ->assertSee('action="/made-cms/pages/'.$page->id.'/restore"', false);
});

it('refuses a page with pages under it', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['title' => 'Over ons']);
    Page::factory()->under($about)->at('/over-ons/team')->create();

    $this->from('/made-cms/pages/'.$about->id)
        ->delete('made-cms/pages/'.$about->id)
        ->assertRedirect('/made-cms/pages/'.$about->id)
        ->assertSessionHasErrors(['delete' => 'Onder deze pagina liggen nog pagina’s. Verplaats of verwijder die eerst.']);

    expect(Page::query()->find($about->id))->not->toBeNull();

    $this->get('made-cms/pages/'.$about->id)->assertSee('Onder deze pagina liggen nog pagina’s.');
});

it('keeps the home page', function (): void {
    $home = Page::factory()->at('/')->create();

    $this->from('/made-cms/pages/'.$home->id)
        ->delete('made-cms/pages/'.$home->id)
        ->assertSessionHasErrors(['delete' => 'De homepagina kan niet weg.']);
});

it('sends a guest to the login page', function (): void {
    auth('made-cms')->logout();
    $page = Page::factory()->at('/over-ons')->create();

    $this->delete('made-cms/pages/'.$page->id)->assertRedirect(route('made-cms-package.login'));

    expect(Page::query()->find($page->id))->not->toBeNull();
});
