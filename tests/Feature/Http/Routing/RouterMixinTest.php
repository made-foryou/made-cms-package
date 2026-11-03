<?php

declare(strict_types=1);

use Illuminate\Routing\CompiledRouteCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Middleware\ResolvePage;
use Made\CmsPackage\Tests\Fixtures\Http\ShowPage;

/**
 * Registers the page routes the way a site does: the macro as the last line of its web routes.
 */
function servePages(Closure|string|null $responder = null): Illuminate\Routing\Route
{
    $route = null;

    Route::middleware('web')->group(function () use ($responder, &$route): void {
        $route = Route::madeCms($responder ?? static fn (Page $page): string => 'Pagina: '.$page->title);
    });

    /** @var Illuminate\Routing\Route $route */
    return $route;
}

/**
 * Does what `php artisan route:cache` does and loads the result back, without a separate process.
 */
function cacheRoutes(): void
{
    $router = app('router');
    $routes = $router->getRoutes();
    $routes->refreshNameLookups();
    $routes->refreshActionLookups();

    foreach ($routes->getRoutes() as $route) {
        $route->prepareForSerialization();
    }

    $file = sys_get_temp_dir().'/made-cms-routes-'.Str::random(16).'.php';
    file_put_contents($file, '<?php return '.var_export($routes->compile(), true).';');

    try {
        /** @var array{compiled: array<mixed>, attributes: array<string, mixed>} $compiled */
        $compiled = require $file;
        $router->setCompiledRoutes($compiled);
    } finally {
        unlink($file);
    }

    expect($router->getRoutes())->toBeInstanceOf(CompiledRouteCollection::class);
}

it('registers a get and head catch-all that looks up the page', function (): void {
    $route = servePages();

    expect($route->uri())->toBe('{path?}')
        ->and($route->methods())->toBe(['GET', 'HEAD'])
        ->and($route->wheres)->toBe(['path' => '.*'])
        ->and($route->gatherMiddleware())->toBe(['web', ResolvePage::class])
        ->and($route->getName())->toBeNull();
});

it('returns the route so the site can name it and add middleware', function (): void {
    servePages()->name('pagina')->middleware('throttle:60,1');

    expect(route('pagina', ['path' => 'over-ons/team'], false))->toBe('/over-ons/team')
        ->and(Route::getRoutes()->getByName('pagina')?->gatherMiddleware())->toContain('throttle:60,1');
});

it('serves the published home page', function (): void {
    Page::factory()->published()->at('/')->create(['title' => 'Home']);
    servePages();

    $this->get('/')->assertOk()->assertSeeText('Pagina: Home');
});

it('serves a published page at a nested path', function (): void {
    Page::factory()->published()->at('/over-ons/team')->create(['title' => 'Team']);
    servePages();

    $this->get('over-ons/team')->assertOk()->assertSeeText('Pagina: Team');
});

it('serves a page for a head request', function (): void {
    Page::factory()->published()->at('/over-ons')->create();
    servePages();

    $this->head('over-ons')->assertOk();
});

it('ignores a trailing slash', function (): void {
    Page::factory()->published()->at('/over-ons')->create(['title' => 'Over ons']);
    servePages();

    $this->get('over-ons/')->assertOk()->assertSeeText('Pagina: Over ons');
});

it('ignores uppercase letters in the address', function (): void {
    Page::factory()->published()->at('/over-ons')->create(['title' => 'Over ons']);
    servePages();

    $this->get('Over-Ons')->assertOk()->assertSeeText('Pagina: Over ons');
});

it('answers 404 when no page lives at the path', function (): void {
    Page::factory()->published()->at('/over-ons')->create();
    servePages();

    $this->get('bestaat-niet')->assertNotFound();
});

it('answers 404 to a visitor for a draft page', function (): void {
    Page::factory()->at('/concept')->create();
    servePages();

    $this->get('concept')->assertNotFound();
});

it('lets a route of the site above the macro win', function (): void {
    Page::factory()->published()->at('/contact')->create();
    Route::get('contact', static fn (): string => 'Formulier van de site');
    servePages();

    $this->get('contact')->assertOk()->assertSeeText('Formulier van de site');
});

it('lets the macro shadow a route of the site below it', function (): void {
    servePages();
    Route::get('contact', static fn (): string => 'Formulier van de site');

    $this->get('contact')->assertNotFound();
});

it('takes over from a fallback route of the site for get requests', function (): void {
    Route::fallback(static fn (): string => 'Fallback van de site');
    servePages();

    $this->get('bestaat-niet')->assertNotFound();
});

it('answers 405 to a post to an address without a route of its own', function (): void {
    servePages();

    $this->post('bestaat-niet')->assertMethodNotAllowed();
});

it('serves pages under the prefix of the surrounding group', function (): void {
    Page::factory()->published()->at('/over-ons')->create(['title' => 'Over ons']);

    Route::middleware('web')->prefix('nl')->group(function (): void {
        Route::madeCms(static fn (Page $page): string => 'NL: '.$page->title);
    });

    $this->get('nl/over-ons')->assertOk()->assertSeeText('NL: Over ons');
    $this->get('over-ons')->assertNotFound();
});

it('calls an invokable responder class', function (): void {
    Page::factory()->published()->at('/over-ons')->create(['title' => 'Over ons']);
    servePages(ShowPage::class);

    $this->get('over-ons')->assertOk()->assertSeeText('Klasse: Over ons');
});

it('leaves the routes of the cms itself alone', function (): void {
    servePages();

    $this->getJson('made-cms/session')->assertOk();
});

it('serves pages from cached routes with a closure responder', function (): void {
    Page::factory()->published()->at('/over-ons')->create(['title' => 'Over ons']);
    servePages();

    cacheRoutes();

    $this->get('over-ons')->assertOk()->assertSeeText('Pagina: Over ons');
    $this->get('bestaat-niet')->assertNotFound();
});

it('serves pages from cached routes with an invokable responder', function (): void {
    Page::factory()->published()->at('/over-ons')->create(['title' => 'Over ons']);
    servePages(ShowPage::class);

    cacheRoutes();

    $this->get('over-ons')->assertOk()->assertSeeText('Klasse: Over ons');
});

it('caches the macro used twice without a name clash', function (): void {
    Page::factory()->published()->at('/over-ons')->create(['title' => 'Over ons']);

    Route::middleware('web')->prefix('en')->group(function (): void {
        Route::madeCms(static fn (Page $page): string => 'EN: '.$page->title);
    });
    servePages();

    cacheRoutes();

    $this->get('en/over-ons')->assertOk()->assertSeeText('EN: Over ons');
    $this->get('over-ons')->assertOk()->assertSeeText('Pagina: Over ons');
});
