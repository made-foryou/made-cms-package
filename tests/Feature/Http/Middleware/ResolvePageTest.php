<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\RenderStrips;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Middleware\ResolvePage;

beforeEach(function (): void {
    useStripTypes();

    Route::middleware(['web', ResolvePage::class])->group(function (): void {
        Route::get('kop/{path?}', fn (Page $page): string => 'Kop: '.RenderStrips::run($page)->strips[0]->kop)->where('path', '.*');
        Route::get('eigen-frame/{path?}', fn (Page $page) => response('Pagina: '.$page->title)->header('X-Frame-Options', 'DENY'))->where('path', '.*');
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

it('hands a draft page to the route in the edit mode for an editor who opens the pages screen', function (): void {
    Page::factory()->at('/concept')->create(['title' => 'Concept']);

    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('concept?made-cms=edit')
        ->assertOk()
        ->assertSeeText('Pagina: Concept');
});

it('renders the draft strips of a published page in the edit mode', function (): void {
    Page::factory()->published()->at('/over-ons')
        ->withStrips([storedStrip('hero', ['kop' => 'Live'], id: 'a')])
        ->withDraft([storedStrip('hero', ['kop' => 'Concept'], id: 'a')])
        ->create();

    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('kop/over-ons?made-cms=edit')
        ->assertOk()
        ->assertSeeText('Kop: Concept');
});

it('ignores the edit parameter for a visitor', function (): void {
    Page::factory()->at('/concept')->create();
    Page::factory()->published()->at('/over-ons')
        ->withStrips([storedStrip('hero', ['kop' => 'Live'], id: 'a')])
        ->withDraft([storedStrip('hero', ['kop' => 'Concept'], id: 'a')])
        ->create();

    $this->get('concept?made-cms=edit')->assertNotFound();
    $this->get('kop/over-ons?made-cms=edit')->assertOk()->assertSeeText('Kop: Live');
});

it('ignores any other value of the parameter', function (): void {
    Page::factory()->at('/concept')->create();

    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('concept?made-cms=preview')
        ->assertNotFound();
});

it('answers 404 in the edit mode for an address without a page', function (): void {
    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('bestaat-niet?made-cms=edit')
        ->assertNotFound();
});

it('sends the edit mode with no-store, sameorigin and noindex headers', function (): void {
    Page::factory()->at('/concept')->create();

    $response = $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('concept?made-cms=edit')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeaderMissing('ETag');

    expect($response->headers->getCacheControlDirective('no-store'))->toBeTrue()
        ->and($response->headers->getCacheControlDirective('private'))->toBeTrue()
        ->and($response->headers->hasCacheControlDirective('public'))->toBeFalse();
});

it('replaces a frame option the response of the site set', function (): void {
    Page::factory()->published()->at('/over-ons')->create();

    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('eigen-frame/over-ons?made-cms=edit')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});

it('leaves the headers of a visitor alone', function (): void {
    Page::factory()->published()->at('/over-ons')->create();

    $this->get('eigen-frame/over-ons?made-cms=edit')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeaderMissing('X-Robots-Tag');
});

it('leaves the edit mode behind once the response is sent', function (): void {
    Page::factory()->published()->at('/over-ons')
        ->withStrips([storedStrip('hero', ['kop' => 'Live'], id: 'a')])
        ->withDraft([storedStrip('hero', ['kop' => 'Concept'], id: 'a')])
        ->create();
    $this->actingAs(Editor::factory()->create(), 'made-cms');

    $this->get('kop/over-ons?made-cms=edit')->assertSeeText('Kop: Concept');
    $this->get('kop/over-ons')->assertSeeText('Kop: Live');
});
