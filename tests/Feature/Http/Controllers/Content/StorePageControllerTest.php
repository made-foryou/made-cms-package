<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('creates the page and shows that it is ready', function (): void {
    $response = $this->from('/made-cms/pages/new')->post('made-cms/pages', ['title' => 'Jeugd', 'slug' => '', 'parent_id' => '', 'in_menu' => '1']);

    $page = Page::query()->with('route')->sole();

    $response->assertRedirect('/made-cms/pages/'.$page->id)
        ->assertSessionHas('made-cms-package.page_created', $page->id);

    expect($page->title)->toBe('Jeugd')
        ->and($page->route?->path)->toBe('/jeugd')
        ->and($page->in_menu)->toBeTrue();
});

it('takes the slug, the parent and the menu flag the editor chose', function (): void {
    $about = Page::factory()->at('/over-ons')->create();

    $this->post('made-cms/pages', ['title' => 'Jeugd', 'slug' => 'Onze Jeugd', 'parent_id' => (string) $about->id, 'in_menu' => '0']);

    $page = Page::query()->with('route')->whereKeyNot($about->id)->sole();

    expect($page->route?->path)->toBe('/over-ons/onze-jeugd')
        ->and($page->parent_id)->toBe($about->id)
        ->and($page->in_menu)->toBeFalse();
});

it('asks for a name', function (): void {
    $this->from('/made-cms/pages/new')->post('made-cms/pages', ['title' => ' '])
        ->assertRedirect('/made-cms/pages/new')
        ->assertSessionHasErrors(['title' => 'Geef de pagina een naam.']);

    expect(Page::query()->count())->toBe(0);
});

it('shows the error and what the editor typed on the form', function (): void {
    Page::factory()->at('/jeugd')->create();

    $this->from('/made-cms/pages/new')->post('made-cms/pages', ['title' => 'Jeugd', 'slug' => '', 'in_menu' => '1'])
        ->assertSessionHasErrors(['slug' => 'Dat adres heeft al een andere pagina.']);

    $this->get('/made-cms/pages/new')
        ->assertSee('value="Jeugd"', false)
        ->assertSee('class="veld fout"', false)
        ->assertSee('Dat adres heeft al een andere pagina.');
});

it('refuses a slug that leaves nothing', function (): void {
    $this->from('/made-cms/pages/new')->post('made-cms/pages', ['title' => 'Jeugd', 'slug' => '???'])
        ->assertSessionHasErrors(['slug' => 'Een adres bestaat uit kleine letters, cijfers en streepjes, zoals over-ons.']);
});

it('refuses a parent that does not exist', function (): void {
    $this->from('/made-cms/pages/new')->post('made-cms/pages', ['title' => 'Jeugd', 'parent_id' => (string) missingId()])
        ->assertSessionHasErrors('parent_id');
});

it('sends a guest to the login page', function (): void {
    auth('made-cms')->logout();

    $this->post('made-cms/pages', ['title' => 'Jeugd'])->assertRedirect(route('made-cms-package.login'));

    expect(Page::query()->count())->toBe(0);
});
