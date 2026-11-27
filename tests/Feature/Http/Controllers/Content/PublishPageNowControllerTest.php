<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
    useStripTypes();
    $this->actingAs(Editor::factory()->create(), 'made-cms');

    $this->draft = [storedStrip('cards', ['kaarten' => []], id: 'kaarten')];
    $this->page = Page::factory()->published()->withDraft($this->draft)->at('/teams')
        ->scheduledAt(now()->addDay())->create(['title' => 'Teams']);
});

function publishNow(Page $page): TestResponse
{
    return test()->from('/made-cms/pages/'.$page->id)->post('made-cms/pages/'.$page->id.'/publish-now');
}

it('puts the draft live at once, takes the planned publication away and says so', function (): void {
    publishNow($this->page)->assertRedirect('/made-cms/pages/'.$this->page->id);

    $page = Page::query()->findOrFail($this->page->id);

    expect($page->strips)->toBeStoredAs($this->draft)
        ->and($page->draft_strips)->toBeNull()
        ->and($page->publish_at)->toBeNull();

    $this->get('made-cms/pages/'.$this->page->id)->assertSee('Teams staat live.');
});

it('notes the editor who publishes the page', function (): void {
    $editor = Editor::factory()->create();
    $this->actingAs($editor, 'made-cms');

    publishNow($this->page);

    expect(Page::query()->findOrFail($this->page->id)->updated_by)->toBe($editor->id);
});

it('goes back to the basics when a strip stops the publication, and keeps the plan', function (): void {
    $this->page->update(['draft_strips' => [storedStrip('hero', ['kop' => null], id: 'hero')]]);

    publishNow($this->page)
        ->assertRedirect('/made-cms/pages/'.$this->page->id.'#made-cms-basis')
        ->assertSessionHasErrors(['publish' => 'Niet gepubliceerd: een strook houdt hem tegen. Open de pagina op de site om te zien welke.']);

    $page = Page::query()->findOrFail($this->page->id);

    expect($page->draft_strips)->not->toBeNull()
        ->and($page->publish_at)->not->toBeNull();
});

it('says when there is nothing to publish', function (): void {
    $this->page->update(['draft_strips' => null]);

    publishNow($this->page)
        ->assertRedirect('/made-cms/pages/'.$this->page->id.'#made-cms-basis')
        ->assertSessionHasErrors(['publish' => 'Er is niets te publiceren: deze pagina is al live zoals hij is.']);
});

it('opens for every editor who opens the pages', function (Role $role): void {
    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms');

    publishNow($this->page)->assertRedirect();

    expect(Page::query()->findOrFail($this->page->id)->draft_strips)->toBeNull();
})->with([
    'admin' => [Role::Admin],
    'webmaster' => [Role::Webmaster],
    'content manager' => [Role::ContentManager],
    'seo specialist' => [Role::SeoSpecialist],
    'owner' => [Role::Owner],
]);

it('refuses an editor without a role', function (): void {
    $this->actingAs(Editor::factory()->withRoles()->create(), 'made-cms');

    publishNow($this->page)->assertForbidden();

    expect(Page::query()->findOrFail($this->page->id)->status)->toBe(PageStatus::Published)
        ->and(Page::query()->findOrFail($this->page->id)->draft_strips)->not->toBeNull();
});

it('sends a guest to the login page', function (): void {
    auth('made-cms')->logout();

    publishNow($this->page)->assertRedirect(route('made-cms-package.login'));

    expect(Page::query()->findOrFail($this->page->id)->draft_strips)->not->toBeNull();
});
