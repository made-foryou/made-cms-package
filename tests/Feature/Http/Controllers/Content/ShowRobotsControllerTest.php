<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'https://ttvmerwestad.nl', 'made-cms-package.sitemap.robots' => []]);
});

it('lets every robot in by default and points them at the sitemap', function (): void {
    servePages();

    $this->get('robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertContent("User-agent: *\nDisallow:\n\nSitemap: https://ttvmerwestad.nl/sitemap.xml\n");
});

it('writes the rules of the developer per robot, saying who set them', function (): void {
    config(['made-cms-package.sitemap.robots' => [
        'Googlebot' => ['/zoeken$'],
        '*' => ['/api/', '/cms/'],
        'googlebot' => ['/*.pdf$'],
    ]]);
    servePages();

    $this->get('robots.txt')->assertOk()->assertContent(implode("\n", [
        'User-agent: *',
        '# Ingesteld door de developer',
        'Disallow: /api/',
        'Disallow: /cms/',
        '',
        'User-agent: Googlebot',
        '# Ingesteld door de developer',
        'Disallow: /zoeken$',
        'Disallow: /*.pdf$',
        '',
        'Sitemap: https://ttvmerwestad.nl/sitemap.xml',
        '',
    ]));
});

it('names the sitemap of every route group', function (): void {
    servePages();
    Route::middleware('web')->prefix('en')->group(function (): void {
        Route::madeCms(static fn (Page $page): string => $page->title);
    });

    $this->get('robots.txt')->assertOk()->assertSee("Sitemap: https://ttvmerwestad.nl/sitemap.xml\nSitemap: https://ttvmerwestad.nl/en/sitemap.xml\n", false);
});

it('serves no robots.txt under a prefix, because robots only read the one at the root', function (): void {
    Route::middleware('web')->prefix('nl')->group(function (): void {
        Route::madeCms(static fn (Page $page): string => $page->title);
    });

    $this->get('nl/robots.txt')->assertNotFound();
    $this->get('robots.txt')->assertNotFound();
});

it('lets a route of the site for robots.txt above the macro win', function (): void {
    Route::middleware('web')->group(function (): void {
        Route::get('robots.txt', static fn (): string => 'Regels van de site');
        Route::madeCms(static fn (Page $page): string => $page->title);
    });

    $this->get('robots.txt')->assertOk()->assertSeeText('Regels van de site');
});

it('never fails on config it cannot write', function (): void {
    config(['made-cms-package.sitemap.robots' => ['*' => ["/x\nDisallow: /", 12], 'Bot' => '/een']]);
    servePages();

    $this->get('robots.txt')->assertOk()->assertContent(implode("\n", [
        'User-agent: *',
        'Disallow:',
        '',
        'User-agent: Bot',
        '# Ingesteld door de developer',
        'Disallow: /een',
        '',
        'Sitemap: https://ttvmerwestad.nl/sitemap.xml',
        '',
    ]));
});
