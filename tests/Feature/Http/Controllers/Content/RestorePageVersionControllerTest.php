<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\DeletePage;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\PageVersion;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
    useStripTypes();
    $this->actingAs(Editor::factory()->create(), 'made-cms');

    $this->older = [storedStrip('hero', ['kop' => 'Vroeger'], id: 'hero')];
    $this->live = [storedStrip('hero', ['kop' => 'Nu live'], id: 'hero')];
    $this->page = Page::factory()->published()->at('/team')->withStrips($this->live)->create(['title' => 'Team']);
    PageVersion::factory()->for($this->page)->number(1)->withStrips($this->older)->create();
    PageVersion::factory()->for($this->page)->number(2)->withStrips($this->live)->create();
});

function restoreVersion(Page $page, int $number): TestResponse
{
    return test()->post('made-cms/pages/'.$page->id.'/versions/'.$number.'/restore');
}

it('puts the version back as the draft and says so in its card', function (): void {
    restoreVersion($this->page, 1)->assertRedirect('/made-cms/pages/'.$this->page->id.'#made-cms-versies');

    expect(Page::query()->findOrFail($this->page->id)->draft_strips)->toBeStoredAs($this->older);

    $this->get('made-cms/pages/'.$this->page->id)
        ->assertSee('<div class="melding" role="status">', false)
        ->assertSee('Versie 1 staat klaar als concept. Kijk hem na op de site en publiceer hem daar.')
        ->assertSee('<a class="tekstlink klein" href="/made-cms/pages/'.$this->page->id.'/edit">Bewerken op de site</a>', false);

    $this->get('made-cms/pages/'.$this->page->id)->assertDontSee('Versie 1 staat klaar als concept.');
});

it('says that the draft that was there is gone', function (): void {
    $this->page->update(['draft_strips' => [storedStrip('hero', ['kop' => 'Half af'], id: 'hero')]]);

    restoreVersion($this->page, 1);

    $this->get('made-cms/pages/'.$this->page->id)
        ->assertSee('Versie 1 staat klaar als concept; het concept dat er stond is weg. Kijk hem na op de site en publiceer hem daar.');
});

it('says when the version is what visitors see', function (): void {
    $this->page->update(['draft_strips' => [storedStrip('hero', ['kop' => 'Half af'], id: 'hero')]]);

    restoreVersion($this->page, 2);

    expect(Page::query()->findOrFail($this->page->id)->draft_strips)->toBeNull();

    $this->get('made-cms/pages/'.$this->page->id)
        ->assertSee('Versie 2 is gelijk aan wat nu live staat. Het concept dat er stond is weg, dus er staat nu geen concept.');
});

it('notes the editor who put the version back', function (): void {
    $editor = Editor::factory()->create();

    $this->actingAs($editor, 'made-cms');
    restoreVersion($this->page, 1);

    expect(Page::query()->findOrFail($this->page->id)->updated_by)->toBe($editor->id);
});

it('answers 404 for a version of another page, a number the page does not have and a deleted page', function (): void {
    PageVersion::factory()->for(Page::factory())->number(3)->create();

    restoreVersion($this->page, 3)->assertNotFound();
    restoreVersion($this->page, 9)->assertNotFound();

    DeletePage::run($this->page);

    restoreVersion($this->page, 1)->assertNotFound();
});

it('opens for every editor who opens the pages', function (Role $role): void {
    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms');

    restoreVersion($this->page, 1)->assertRedirect();

    expect(Page::query()->findOrFail($this->page->id)->draft_strips)->not->toBeNull();
})->with([
    'admin' => [Role::Admin],
    'webmaster' => [Role::Webmaster],
    'content manager' => [Role::ContentManager],
    'seo specialist' => [Role::SeoSpecialist],
    'owner' => [Role::Owner],
]);

it('refuses an editor without a role', function (): void {
    $this->actingAs(Editor::factory()->withRoles()->create(), 'made-cms');

    restoreVersion($this->page, 1)->assertForbidden();

    expect(Page::query()->findOrFail($this->page->id)->draft_strips)->toBeNull();
});

it('sends a guest to the login page', function (): void {
    auth('made-cms')->logout();

    restoreVersion($this->page, 1)->assertRedirect(route('made-cms-package.login'));

    expect(Page::query()->findOrFail($this->page->id)->draft_strips)->toBeNull();
});
