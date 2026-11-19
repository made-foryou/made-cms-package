<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Enums\SitemapCause;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Sitemap;
use Made\CmsPackage\Content\Sitemap\SitemapXml;

beforeEach(function (): void {
    config(['app.url' => 'https://ttvmerwestad.nl']);
});

it('serves the sitemap of the site as xml Google accepts', function (): void {
    Page::factory()->published()->at('/')->create();
    Page::factory()->published()->at('/teams')->create();
    servePages();

    $response = $this->get('sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $document = new DOMDocument;
    $document->loadXML((string) $response->getContent());

    expect(SitemapXml::locations((string) $response->getContent()))->toBe(['https://ttvmerwestad.nl/', 'https://ttvmerwestad.nl/teams'])
        ->and($document->schemaValidate(__DIR__.'/../../../../Fixtures/Sitemap/sitemap.xsd'))->toBeTrue();
});

it('builds the sitemap at the first request for it, and serves the stored one after that', function (): void {
    Page::factory()->published()->at('/')->create();
    servePages();

    $this->get('sitemap.xml')->assertOk();
    Page::factory()->published()->at('/teams')->create();
    $again = $this->get('sitemap.xml')->assertOk();

    expect(Sitemap::query()->count())->toBe(1)
        ->and(Sitemap::query()->firstOrFail()->cause)->toBe(SitemapCause::Requested)
        ->and($again->getContent())->not->toContain('/teams');
});

it('says when it was built, and answers that nothing changed since', function (): void {
    servePages();
    Sitemap::factory()->create(['built_at' => CarbonImmutable::parse('2026-11-06 10:20:00')]);

    $this->get('sitemap.xml')->assertOk()->assertHeader('Last-Modified', 'Fri, 06 Nov 2026 10:20:00 GMT');

    $this->get('sitemap.xml', ['If-Modified-Since' => 'Fri, 06 Nov 2026 10:20:00 GMT'])
        ->assertStatus(304)
        ->assertContent('');
    $this->get('sitemap.xml', ['If-Modified-Since' => 'Fri, 06 Nov 2026 10:19:59 GMT'])->assertOk();
});

it('serves the sitemap for a head request', function (): void {
    servePages();

    $this->head('sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
});

it('serves the sitemap of a route group under its prefix, with the addresses of that group', function (): void {
    Page::factory()->published()->at('/')->create();
    Page::factory()->published()->at('/over-ons')->create();
    Route::middleware('web')->prefix('nl')->group(function (): void {
        Route::madeCms(static fn (Page $page): string => $page->title);
    });

    $response = $this->get('nl/sitemap.xml')->assertOk();

    expect(SitemapXml::locations((string) $response->getContent()))->toBe(['https://ttvmerwestad.nl/nl', 'https://ttvmerwestad.nl/nl/over-ons'])
        ->and(Sitemap::query()->firstOrFail()->prefix)->toBe('nl');
    $this->get('sitemap.xml')->assertNotFound();
});

it('serves a sitemap per route group, also from cached routes', function (): void {
    Page::factory()->published()->at('/over-ons')->create();
    Route::middleware('web')->prefix('en')->group(function (): void {
        Route::madeCms(static fn (Page $page): string => $page->title);
    });
    servePages();

    cacheRoutes();

    expect(SitemapXml::locations((string) $this->get('en/sitemap.xml')->assertOk()->getContent()))->toBe(['https://ttvmerwestad.nl/en/over-ons'])
        ->and(SitemapXml::locations((string) $this->get('sitemap.xml')->assertOk()->getContent()))->toBe(['https://ttvmerwestad.nl/over-ons']);
});

it('caches the routes of a named route group', function (): void {
    Route::middleware('web')->name('site.')->group(function (): void {
        Route::madeCms(static fn (Page $page): string => $page->title);
    });

    cacheRoutes();

    $this->get('sitemap.xml')->assertOk();
    $this->get('robots.txt')->assertOk();
});

it('never sends the sitemap elsewhere, never adds the launcher and never asks to log in', function (): void {
    servePages();

    requestAsTyped('/sitemap.xml/')->assertOk();
    $response = $this->actingAs(Editor::factory()->create(), 'made-cms')->get('sitemap.xml')->assertOk();

    expect($response->getContent())->not->toContain('made-cms-launcher');
});

it('lets a route of the site for the sitemap above the macro win', function (): void {
    Route::middleware('web')->group(function (): void {
        Route::get('sitemap.xml', static fn (): string => 'Sitemap van de site');
        Route::madeCms(static fn (Page $page): string => $page->title);
    });

    $this->get('sitemap.xml')->assertOk()->assertSeeText('Sitemap van de site');
});

it('lets a page at the address of the sitemap give way to the sitemap', function (): void {
    Page::factory()->published()->at('/sitemap.xml')->create(['title' => 'Pagina']);
    servePages();

    $this->get('sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
});
