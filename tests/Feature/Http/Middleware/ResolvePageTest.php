<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Middleware\ResolvePage;

beforeEach(function (): void {
    Route::middleware(['web', ResolvePage::class])->group(function (): void {
        Route::get('anders/{path?}', fn (Page $whatever): string => 'Anders: '.$whatever->title)->where('path', '.*');
        Route::get('verzoek/{path?}', fn (Request $request, Page $page): string => $request->method().' '.$page->title)->where('path', '.*');
        Route::get('{path?}', fn (Page $page): string => 'Pagina: '.$page->title)->where('path', '.*');
    });
});

it('hands the published page at the path to the route', function (): void {
    Page::factory()->published()->at('/over-ons/team')->create(['title' => 'Team']);

    $this->get('over-ons/team')->assertOk()->assertSeeText('Pagina: Team');
});

it('hands the home page to the route when the path is empty', function (): void {
    Page::factory()->published()->at('/')->create(['title' => 'Home']);

    $this->get('/')->assertOk()->assertSeeText('Pagina: Home');
});

it('answers 404 when no page lives at the path', function (): void {
    Page::factory()->published()->at('/over-ons')->create();

    $this->get('bestaat-niet')->assertNotFound();
});

it('answers 404 for a draft page', function (): void {
    Page::factory()->at('/concept')->create();

    $this->get('concept')->assertNotFound();
});

it('hands the page over whatever the parameter of the route is called', function (): void {
    Page::factory()->published()->at('/over-ons')->create(['title' => 'Over ons']);

    $this->get('anders/over-ons')->assertOk()->assertSeeText('Anders: Over ons');
});

it('resolves other dependencies of the route next to the page', function (): void {
    Page::factory()->published()->at('/over-ons')->create(['title' => 'Over ons']);

    $this->get('verzoek/over-ons')->assertOk()->assertSeeText('GET Over ons');
});
