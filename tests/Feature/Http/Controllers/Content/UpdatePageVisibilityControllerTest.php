<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('hides the page from visitors at its own address', function (): void {
    $page = Page::factory()->published()->at('/privacy')->create();

    expect(route('made-cms-package.pages.visibility', ['page' => $page], false))->toBe('/made-cms/pages/'.$page->id.'/visibility');
});

it('hides the page and says so on its screen', function (): void {
    $page = Page::factory()->published()->at('/privacy')->create(['title' => 'Privacy']);

    $this->put('made-cms/pages/'.$page->id.'/visibility', ['visible' => '0'])->assertRedirect('/made-cms/pages/'.$page->id);

    expect($page->refresh()->hidden)->toBeTrue();

    $this->get('made-cms/pages/'.$page->id)
        ->assertSee('Privacy is verborgen voor bezoekers.')
        ->assertSee('<div class="bericht" role="status">', false);
});

it('shows a hidden page again and says so', function (): void {
    $page = Page::factory()->published()->hidden()->at('/privacy')->create(['title' => 'Privacy']);

    $this->put('made-cms/pages/'.$page->id.'/visibility', ['visible' => '1'])->assertRedirect('/made-cms/pages/'.$page->id);

    expect($page->refresh()->hidden)->toBeFalse();

    $this->get('made-cms/pages/'.$page->id)->assertSee('Privacy is weer zichtbaar voor bezoekers.');
});

it('refuses without a choice', function (): void {
    $page = Page::factory()->published()->at('/privacy')->create();

    $this->from('/made-cms/pages/'.$page->id)
        ->put('made-cms/pages/'.$page->id.'/visibility', [])
        ->assertRedirect('/made-cms/pages/'.$page->id)
        ->assertSessionHasErrors(['visible' => 'Kies of bezoekers de pagina zien.']);

    expect($page->refresh()->hidden)->toBeFalse();
});

it('keeps the home page visible', function (): void {
    $home = Page::factory()->published()->at('/')->create();

    $this->from('/made-cms/pages/'.$home->id)
        ->put('made-cms/pages/'.$home->id.'/visibility', ['visible' => '0'])
        ->assertRedirect('/made-cms/pages/'.$home->id)
        ->assertSessionHasErrors(['visible' => 'De homepagina blijft zichtbaar voor bezoekers.']);

    expect($home->refresh()->hidden)->toBeFalse();
});

it('sends a guest to the login page', function (): void {
    auth('made-cms')->logout();
    $page = Page::factory()->published()->at('/privacy')->create();

    $this->put('made-cms/pages/'.$page->id.'/visibility', ['visible' => '0'])->assertRedirect(route('made-cms-package.login'));

    expect($page->refresh()->hidden)->toBeFalse();
});

it('notes the editor who hides the page', function (): void {
    $editor = Editor::factory()->create();
    $this->actingAs($editor, 'made-cms');
    $page = Page::factory()->published()->at('/privacy')->create();

    $this->put('made-cms/pages/'.$page->id.'/visibility', ['visible' => '0']);

    expect($page->refresh()->updated_by)->toBe($editor->id);
});
