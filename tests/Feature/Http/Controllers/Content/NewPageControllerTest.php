<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'https://ttvmerwestad.nl', 'app.name' => 'TTV Merwestad']);
});

function newPageScreen(string $query = ''): string
{
    return (string) test()->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('made-cms/pages/new'.$query)
        ->assertOk()
        ->getContent();
}

it('opens the new page at its own address', function (): void {
    expect(route('made-cms-package.pages.create', [], false))->toBe('/made-cms/pages/new');
});

it('asks for the name and the place in two steps of one form', function (): void {
    expect(newPageScreen())
        ->toContain('<title>Nieuwe pagina · TTV Merwestad</title>')
        ->toContain('<form class="formulier" method="post" action="/made-cms/pages" data-wizard>')
        ->toContain('data-stap="1"')
        ->toContain('Hoe heet de pagina?')
        ->toContain('data-stap="2"')
        ->toContain('Waar hoort <span data-title-echo>de pagina</span> thuis?')
        ->toContain('ttvmerwestad.nl')
        ->toContain('Pagina aanmaken')
        ->toContain('href="/made-cms/pages">Toch niet</a>');
});

it('counts two steps and asks no type while the site defines no page types', function (): void {
    expect(newPageScreen())
        ->toContain('data-tekst-2="Stap 2 van 2"')
        ->not->toContain('data-stap="3"')
        ->not->toContain('name="type"')
        ->toContain('<h1 id="made-cms-titel">Hoe heet de pagina?</h1>');
});

it('asks the type first, an ordinary page chosen, when the site defines page types', function (): void {
    usePageTypes();

    $screen = newPageScreen();

    expect($screen)
        ->toContain('data-tekst-1="Stap 1 van 3"')
        ->toContain('data-tekst-3="Stap 3 van 3"')
        ->toContain('<h1 id="made-cms-titel">Wat voor pagina wordt het?</h1>')
        ->toContain('Dit bepaalt welke stroken je straks op de site kunt gebruiken. Veranderen kan later altijd nog.')
        ->toContain('<input type="radio" name="type" value="" checked>')
        ->toContain('Gewone pagina')
        ->toContain('Vrij in te delen met alle stroken van de site.')
        ->toContain('<input type="radio" name="type" value="team">')
        ->toContain('Teampagina')
        ->toContain('Vaste opzet: een hero en de teamkaarten.')
        ->toContain('<input type="radio" name="type" value="landing">')
        ->toContain('<h2>Hoe heet de pagina?</h2>')
        ->toContain('data-stap="3"');

    expect(strpos($screen, 'Gewone pagina'))->toBeLessThan(strpos($screen, 'Landing Page'))
        ->and(strpos($screen, 'Landing Page'))->toBeLessThan(strpos($screen, 'Teampagina'));
});

it('offers the top and every page as the place, the top chosen', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['title' => 'Over ons']);
    Page::factory()->under($about)->at('/over-ons/team')->create(['title' => 'Team']);

    expect(newPageScreen())
        ->toContain('<input type="radio" name="parent_id" value="" checked>')
        ->toContain('Onder Over ons')
        ->toContain('/over-ons/team/<span data-slug-preview>')
        ->toContain('<input type="radio" name="parent_id" value="'.$about->id.'">');
});

it('chooses the parent from the address', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['title' => 'Over ons']);

    expect(newPageScreen('?parent='.$about->id))
        ->toContain('<input type="radio" name="parent_id" value="'.$about->id.'" checked>')
        ->toContain('<input type="radio" name="parent_id" value="">');
});

it('puts the page in the menu unless the editor says otherwise', function (): void {
    expect(newPageScreen())->toContain('name="in_menu" value="1" checked');
});

it('sends a guest to the login page', function (): void {
    $this->get('made-cms/pages/new')->assertRedirect(route('made-cms-package.login'));
});
