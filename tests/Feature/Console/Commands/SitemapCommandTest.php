<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Made\CmsPackage\Content\Enums\SitemapCause;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Sitemap;

it('builds the sitemap of every route group and says what is in it', function (): void {
    servePages();
    Route::middleware('web')->prefix('en')->group(function (): void {
        Route::madeCms(static fn (Page $page): string => $page->title);
    });
    Page::factory()->published()->at('/')->create();
    Page::factory()->published()->at('/teams')->create();
    Page::factory()->published()->create(['title' => 'Zwerver']);

    $this->artisan('made-cms:sitemap')
        // The group under /en first: its line holds the line of the root as well.
        ->expectsOutputToContain('/en/sitemap.xml: 2 adressen, 1 fout.')
        ->expectsOutputToContain('/sitemap.xml: 2 adressen, 1 fout.')
        ->assertSuccessful();

    expect(Sitemap::query()->pluck('cause')->all())->toBe([SitemapCause::Command, SitemapCause::Command]);
});

it('says when the sitemap stayed the same', function (): void {
    servePages();
    Page::factory()->published()->at('/')->create();
    $this->artisan('made-cms:sitemap')->assertSuccessful();

    $this->artisan('made-cms:sitemap')
        ->expectsOutputToContain('/sitemap.xml is niet veranderd: 1 adres, geen fouten.')
        ->assertSuccessful();

    expect(Sitemap::query()->count())->toBe(1);
});

it('warns when the site serves no pages yet', function (): void {
    $this->artisan('made-cms:sitemap')
        ->expectsOutputToContain('Route::madeCms()')
        ->assertSuccessful();

    expect(Sitemap::query()->count())->toBe(0);
});
