<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

function pageScreen(Page $page): string
{
    return (string) test()->get('made-cms/pages/'.$page->id)->assertOk()->getContent();
}

it('opens a page at its own address', function (): void {
    $page = Page::factory()->at('/over-ons')->create();

    expect(route('made-cms-package.pages.show', ['page' => $page], false))->toBe('/made-cms/pages/'.$page->id);
});

it('answers 404 for a page that does not exist or is deleted', function (): void {
    $deleted = Page::factory()->trashed()->create();

    $this->get('made-cms/pages/999')->assertNotFound();
    $this->get('made-cms/pages/'.$deleted->id)->assertNotFound();
});

it('names the page, its status and its address, and links to the edit shell', function (): void {
    $page = Page::factory()->published()->at('/over-ons')->create(['title' => 'Over ons']);

    expect(pageScreen($page))
        ->toContain('<title>Over ons · TTV Merwestad</title>')
        ->toContain('<h1 id="made-cms-titel">Over ons</h1>')
        ->toContain('<span class="status live">Live</span>')
        ->toContain('localhost/over-ons')
        ->toContain('href="/over-ons" target="_blank" rel="noopener"')
        ->toContain('<a class="knop" href="/made-cms/pages/'.$page->id.'/edit">Bewerken op de site</a>');
});

it('only links to the site for a live page', function (): void {
    $page = Page::factory()->at('/concept')->create();

    expect(pageScreen($page))->not->toContain('Bekijk op de site');
});

it('shows the basics in a form with every other page as a parent', function (): void {
    $home = Page::factory()->atPosition(0)->at('/')->create(['title' => 'Home']);
    $about = Page::factory()->atPosition(1)->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['title' => 'Team']);

    expect(pageScreen($about))
        ->toContain('action="/made-cms/pages/'.$about->id.'"')
        ->toContain('<input type="hidden" name="_method" value="PUT">')
        ->toContain('value="Over ons"')
        ->toContain('value="over-ons"')
        ->toContain('<option value="'.$home->id.'">Home</option>')
        ->not->toContain('<option value="'.$team->id.'"')
        ->toContain('Plek 2 van 2 in het menu.');
});

it('chooses the current parent', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['title' => 'Over ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['title' => 'Team']);

    expect(pageScreen($team))->toContain('<option value="'.$about->id.'" selected>Over ons</option>');
});

it('keeps the address and the place of the home page out of the form, and the home page itself', function (): void {
    $home = Page::factory()->at('/')->create(['title' => 'Home']);

    expect(pageScreen($home))
        ->not->toContain('name="slug"')
        ->not->toContain('name="parent_id"')
        ->not->toContain('Weghalen');
});

it('lists the pages under it and links to a new one below it', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['title' => 'Over ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['title' => 'Team']);

    expect(pageScreen($about))
        ->toContain('href="/made-cms/pages/new?parent='.$about->id.'"')
        ->toContain('/over-ons/team')
        ->toContain('href="/made-cms/pages/'.$team->id.'"');
});

it('only offers to delete a page without pages under it', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['title' => 'Over ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['title' => 'Team']);

    expect(pageScreen($about))
        ->toContain('Onder Over ons liggen nog pagina’s.')
        ->not->toContain('<input type="hidden" name="_method" value="DELETE">');

    expect(pageScreen($team))
        ->toContain('Ja, verwijder Team')
        ->toContain('<input type="hidden" name="_method" value="DELETE">');
});

it('marks the page as current in the menu', function (): void {
    $page = Page::factory()->at('/over-ons')->create(['title' => 'Over ons']);

    expect(pageScreen($page))->toContain('href="/made-cms/pages/'.$page->id.'" aria-current="page"');
});

it('says once that a new page is ready', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['title' => 'Over ons']);

    $this->post('made-cms/pages', ['title' => 'Jeugd', 'parent_id' => (string) $about->id, 'in_menu' => '1']);
    $page = Page::query()->where('title', 'Jeugd')->sole();

    $this->get('made-cms/pages/'.$page->id)
        ->assertSee('Jeugd staat klaar, nog leeg en nog niet zichtbaar.')
        ->assertSee('Hij staat als concept op /over-ons/jeugd, onder Over ons, achteraan in het menu.')
        ->assertSee('href="/made-cms/pages/'.$page->id.'/edit">Nu vullen op de site</a>', false)
        ->assertSee('href="/made-cms/pages/'.$page->id.'">Eerst het overzicht</a>', false)
        ->assertSee('href="/made-cms/pages">Later</a>', false);

    expect(pageScreen($page))->not->toContain('staat klaar');
});
