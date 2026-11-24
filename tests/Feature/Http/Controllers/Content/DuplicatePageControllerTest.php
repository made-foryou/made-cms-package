<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\DeletePage;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * The copy of a page: the newest page.
 */
function copyOfPage(): Page
{
    return Page::query()->with('route')->latest('id')->firstOrFail();
}

it('duplicates the page and opens the copy with a notice', function (): void {
    $page = Page::factory()->at('/team')->create(['title' => 'Team', 'slug' => 'team']);

    $response = $this->post('made-cms/pages/'.$page->id.'/duplicate');

    $copy = copyOfPage();

    expect($copy->id)->not->toBe($page->id)
        ->and($copy->title)->toBe('Team (kopie)')
        ->and($copy->route?->path)->toBe('/team-kopie');

    $response->assertRedirect('/made-cms/pages/'.$copy->id);

    $this->get('made-cms/pages/'.$copy->id)
        ->assertSee('Team (kopie) is gemaakt als kopie van Team.')
        ->assertSee('<div class="bericht" role="status">', false)
        ->assertSee('<h1 id="made-cms-titel">Team (kopie)</h1>', false);
});

it('notes the editor who duplicates the page', function (): void {
    $editor = Editor::factory()->create();
    $this->actingAs($editor, 'made-cms');
    $page = Page::factory()->at('/team')->create(['slug' => 'team']);

    $this->post('made-cms/pages/'.$page->id.'/duplicate');

    expect(copyOfPage()->created_by)->toBe($editor->id)
        ->and(copyOfPage()->updated_by)->toBe($editor->id);
});

it('refuses a copy whose address cannot fit', function (): void {
    $parentPath = '/'.str_repeat('a', 120).'/'.str_repeat('b', 120).'/'.str_repeat('d', 6);
    $parent = Page::factory()->at($parentPath)->create(['slug' => str_repeat('d', 6)]);
    $page = Page::factory()->under($parent)->at($parentPath.'/c')->create(['slug' => 'c']);

    $this->from('/made-cms/pages/'.$page->id)
        ->post('made-cms/pages/'.$page->id.'/duplicate')
        ->assertRedirect('/made-cms/pages/'.$page->id)
        ->assertSessionHasErrors(['duplicate' => 'Het adres van de kopie wordt langer dan 255 tekens. Geef deze pagina of een pagina erboven eerst een korter adres.']);

    expect(Page::query()->count())->toBe(2);
});

it('answers 404 for a page that does not exist or is deleted', function (): void {
    $page = Page::factory()->at('/team')->create(['slug' => 'team']);
    DeletePage::run($page);

    $this->post('made-cms/pages/'.missingId().'/duplicate')->assertNotFound();
    $this->post('made-cms/pages/'.$page->id.'/duplicate')->assertNotFound();
});

it('opens for every editor who opens the pages', function (Role $role): void {
    $page = Page::factory()->at('/team')->create(['slug' => 'team']);

    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->post('made-cms/pages/'.$page->id.'/duplicate')
        ->assertRedirect();

    expect(Page::query()->count())->toBe(2);
})->with([
    'admin' => [Role::Admin],
    'webmaster' => [Role::Webmaster],
    'content manager' => [Role::ContentManager],
    'seo specialist' => [Role::SeoSpecialist],
    'owner' => [Role::Owner],
]);

it('refuses an editor without a role', function (): void {
    $page = Page::factory()->at('/team')->create(['slug' => 'team']);

    $this->actingAs(Editor::factory()->withRoles()->create(), 'made-cms')
        ->post('made-cms/pages/'.$page->id.'/duplicate')
        ->assertForbidden();

    expect(Page::query()->count())->toBe(1);
});

it('sends a guest to the login page', function (): void {
    auth('made-cms')->logout();
    $page = Page::factory()->at('/team')->create(['slug' => 'team']);

    $this->post('made-cms/pages/'.$page->id.'/duplicate')->assertRedirect(route('made-cms-package.login'));

    expect(Page::query()->count())->toBe(1);
});
