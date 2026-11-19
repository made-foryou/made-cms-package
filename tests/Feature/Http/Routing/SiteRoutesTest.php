<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Made\CmsPackage\Content\Data\SitePrefixes;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Routing\SiteRoutes;

function prefixesOfTheSite(): array
{
    return app(SitePrefixes::class)->prefixes;
}

it('finds no route group without Route::madeCms', function (): void {
    Route::get('contact', static fn (): string => 'Contact');

    expect(prefixesOfTheSite())->toBe([]);
});

it('finds the prefix of every route group that serves the pages, the root first', function (): void {
    Route::middleware('web')->prefix('en')->group(function (): void {
        Route::madeCms(static fn (Page $page): string => $page->title);
    });
    Route::middleware('web')->prefix('/site/nl/')->group(function (): void {
        Route::madeCms(static fn (Page $page): string => $page->title);
    });
    servePages();

    expect(prefixesOfTheSite())->toBe(['', 'en', 'site/nl']);
});

it('finds the route groups in cached routes', function (): void {
    Route::middleware('web')->prefix('en')->group(function (): void {
        Route::madeCms(static fn (Page $page): string => $page->title);
    });
    servePages();

    cacheRoutes();

    expect(prefixesOfTheSite())->toBe(['', 'en']);
});

it('knows a get route the site has already', function (): void {
    Route::post('sitemap.xml', static fn (): string => 'Post');
    Route::prefix('nl')->group(function (): void {
        Route::get('sitemap.xml', static fn (): string => 'Sitemap');
    });

    Route::domain('nl.ttvmerwestad.nl')->group(function (): void {
        Route::get('robots.txt', static fn (): string => 'Robots');
    });

    $routes = Route::getRoutes();
    $inDomain = [['domain' => 'nl.ttvmerwestad.nl']];

    expect(SiteRoutes::claimed($routes, [], 'nl/sitemap.xml'))->toBeTrue()
        ->and(SiteRoutes::claimed($routes, [], 'sitemap.xml'))->toBeFalse()
        ->and(SiteRoutes::claimed($routes, [], 'robots.txt'))->toBeFalse()
        ->and(SiteRoutes::claimed($routes, $inDomain, 'robots.txt'))->toBeTrue();
});
