<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\DeletePage;
use Made\CmsPackage\Content\Actions\MovePage;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('restores the page and its address', function (): void {
    $page = Page::factory()->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);
    DeletePage::run($page);

    $this->post('made-cms/pages/'.$page->id.'/restore')->assertRedirect('/made-cms/pages');

    expect(Page::query()->with('route')->find($page->id)?->route?->path)->toBe('/over-ons');

    $this->get('made-cms/pages')
        ->assertSee('Over ons is teruggezet.')
        ->assertDontSee('Verwijderde pagina’s');
});

it('refuses a page whose parent is deleted and says why', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);
    DeletePage::run($team);
    DeletePage::run($about);

    $this->from('/made-cms/pages')
        ->post('made-cms/pages/'.$team->id.'/restore')
        ->assertRedirect('/made-cms/pages')
        ->assertSessionHasErrors('restore');

    $this->get('made-cms/pages')->assertSee('De pagina waar deze onder lag, is ook verwijderd.');
});

it('answers 404 for a page that does not exist', function (): void {
    $this->post('made-cms/pages/'.missingId().'/restore')->assertNotFound();
});

it('refuses a page whose address got longer than 255 characters and says why', function (): void {
    $above = Page::factory()->atPosition(0)->at('/'.str_repeat('b', 120))->create(['slug' => str_repeat('b', 120)]);
    $parent = Page::factory()->atPosition(1)->at('/'.str_repeat('a', 120))->create(['slug' => str_repeat('a', 120)]);
    $page = Page::factory()->under($parent)->at('/'.str_repeat('a', 120).'/'.str_repeat('d', 13))->create(['slug' => str_repeat('d', 13)]);
    DeletePage::run($page);
    MovePage::run($parent, $above->id);

    $this->from('/made-cms/pages')
        ->post('made-cms/pages/'.$page->id.'/restore')
        ->assertRedirect('/made-cms/pages')
        ->assertSessionHasErrors(['restore' => 'Op zijn oude plek wordt het adres langer dan 255 tekens. Geef de pagina erboven eerst een korter adres.']);
});

it('notes the editor who restores the page', function (): void {
    $editor = Editor::factory()->create();
    $this->actingAs($editor, 'made-cms');
    $page = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    DeletePage::run($page);

    $this->post('made-cms/pages/'.$page->id.'/restore');

    expect(Page::query()->findOrFail($page->id)->updated_by)->toBe($editor->id);
});
