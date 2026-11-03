<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('saves the basics and says so', function (): void {
    $page = Page::factory()->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);

    $this->from('/made-cms/pages/'.$page->id)
        ->put('made-cms/pages/'.$page->id, ['title' => 'Wie we zijn', 'slug' => 'wie-we-zijn', 'parent_id' => '', 'in_menu' => '0'])
        ->assertRedirect('/made-cms/pages/'.$page->id);

    expect($page->refresh()->title)->toBe('Wie we zijn')
        ->and($page->route?->path)->toBe('/wie-we-zijn')
        ->and($page->in_menu)->toBeFalse();

    $this->get('made-cms/pages/'.$page->id)->assertSee('Wie we zijn is opgeslagen.');
});

it('moves the page under another parent', function (): void {
    $about = Page::factory()->atPosition(0)->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->atPosition(1)->at('/team')->create(['title' => 'Team', 'slug' => 'team']);

    $this->put('made-cms/pages/'.$team->id, ['title' => 'Team', 'slug' => 'team', 'parent_id' => (string) $about->id, 'in_menu' => '1']);

    expect($team->refresh()->route?->path)->toBe('/over-ons/team');
});

it('refuses a parent under the page itself', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create();

    $this->from('/made-cms/pages/'.$about->id)
        ->put('made-cms/pages/'.$about->id, ['title' => 'Over ons', 'slug' => 'over-ons', 'parent_id' => (string) $team->id, 'in_menu' => '1'])
        ->assertRedirect('/made-cms/pages/'.$about->id)
        ->assertSessionHasErrors('parent_id');

    expect($about->refresh()->parent_id)->toBeNull();
});

it('refuses an address another page has', function (): void {
    Page::factory()->at('/contact')->create();
    $page = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);

    $this->from('/made-cms/pages/'.$page->id)
        ->put('made-cms/pages/'.$page->id, ['title' => 'Over ons', 'slug' => 'contact', 'parent_id' => '', 'in_menu' => '1'])
        ->assertSessionHasErrors(['slug' => 'Dat adres heeft al een andere pagina.']);
});

it('keeps the address of the home page when the form has no address', function (): void {
    $home = Page::factory()->at('/')->create(['title' => 'Home', 'slug' => 'home']);

    $this->put('made-cms/pages/'.$home->id, ['title' => 'Welkom', 'in_menu' => '1'])
        ->assertRedirect('/made-cms/pages/'.$home->id);

    expect($home->refresh()->title)->toBe('Welkom')
        ->and($home->route?->path)->toBe('/');
});
