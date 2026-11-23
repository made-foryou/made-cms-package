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

it('refuses an address that gets longer than 120 characters once it is made', function (): void {
    $page = Page::factory()->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);

    $this->from('/made-cms/pages/'.$page->id)
        ->put('made-cms/pages/'.$page->id, ['title' => 'Over ons', 'slug' => str_repeat('щ', 50), 'parent_id' => '', 'in_menu' => '1'])
        ->assertSessionHasErrors(['slug' => 'Houd het adres op hooguit 120 tekens. Een letter als ß of щ telt daarin als meer tekens.']);

    expect($page->refresh()->slug)->toBe('over-ons')
        ->and($page->route?->path)->toBe('/over-ons');
});

it('refuses an address that makes the address of a page under it longer than 255 characters', function (): void {
    $page = Page::factory()->at('/'.str_repeat('p', 10))->create(['title' => 'Pagina', 'slug' => str_repeat('p', 10)]);
    $child = Page::factory()->under($page)->at('/'.str_repeat('p', 10).'/'.str_repeat('c', 120))->create(['slug' => str_repeat('c', 120)]);
    Page::factory()->under($child)->at('/'.str_repeat('p', 10).'/'.str_repeat('c', 120).'/'.str_repeat('g', 120))->create(['slug' => str_repeat('g', 120)]);

    $this->from('/made-cms/pages/'.$page->id)
        ->put('made-cms/pages/'.$page->id, ['title' => 'Pagina', 'slug' => str_repeat('p', 13), 'parent_id' => '', 'in_menu' => '1'])
        ->assertSessionHasErrors(['slug' => 'Het hele adres wordt dan langer dan 255 tekens, bij deze pagina of een pagina eronder. Kies een korter adres.']);

    expect($page->refresh()->route?->path)->toBe('/'.str_repeat('p', 10));
});

it('keeps the address of the home page when the form has no address', function (): void {
    $home = Page::factory()->at('/')->create(['title' => 'Home', 'slug' => 'home']);

    $this->put('made-cms/pages/'.$home->id, ['title' => 'Welkom', 'in_menu' => '1'])
        ->assertRedirect('/made-cms/pages/'.$home->id);

    expect($home->refresh()->title)->toBe('Welkom')
        ->and($home->route?->path)->toBe('/');
});

it('hides the page with the switch for visitors and shows it again', function (): void {
    $page = Page::factory()->published()->at('/privacy')->create(['title' => 'Privacy', 'slug' => 'privacy']);
    $basics = ['title' => 'Privacy', 'slug' => 'privacy', 'parent_id' => '', 'in_menu' => '1'];

    $this->put('made-cms/pages/'.$page->id, [...$basics, 'visible' => '0'])->assertRedirect('/made-cms/pages/'.$page->id);

    expect($page->refresh()->hidden)->toBeTrue();

    $this->put('made-cms/pages/'.$page->id, [...$basics, 'visible' => '1']);

    expect($page->refresh()->hidden)->toBeFalse();
});

it('keeps whether visitors see the page when the form has no switch for it', function (): void {
    $page = Page::factory()->published()->hidden()->at('/privacy')->create(['title' => 'Privacy', 'slug' => 'privacy']);

    $this->put('made-cms/pages/'.$page->id, ['title' => 'Privacyverklaring', 'slug' => 'privacy', 'parent_id' => '', 'in_menu' => '1']);

    expect($page->refresh()->title)->toBe('Privacyverklaring')
        ->and($page->hidden)->toBeTrue();
});

it('notes the editor who saves the basics', function (): void {
    $editor = Editor::factory()->create();
    $this->actingAs($editor, 'made-cms');
    $page = Page::factory()->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);

    $this->put('made-cms/pages/'.$page->id, ['title' => 'Wie we zijn', 'slug' => 'over-ons', 'parent_id' => '', 'in_menu' => '1']);

    expect($page->refresh()->updated_by)->toBe($editor->id);
});
