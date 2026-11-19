<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\BuildSitemaps;
use Made\CmsPackage\Content\Data\BuiltSitemap;
use Made\CmsPackage\Content\Data\SitemapReason;
use Made\CmsPackage\Content\Data\SitePrefixes;
use Made\CmsPackage\Content\Enums\SitemapCause;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Sitemap;

it('builds the sitemap of every route group of the site', function (): void {
    app()->instance(SitePrefixes::class, new SitePrefixes(['', 'en']));
    Page::factory()->published()->at('/')->create();
    Page::factory()->published()->create(['title' => 'Zwerver']);

    $built = BuildSitemaps::run(new SitemapReason(SitemapCause::Command));

    expect(array_map(fn (BuiltSitemap $sitemap): array => [$sitemap->prefix, $sitemap->addresses, $sitemap->errors, $sitemap->changed], $built))->toBe([
        ['', 1, 1, true],
        ['en', 1, 1, true],
    ])->and(Sitemap::query()->orderBy('prefix')->pluck('prefix')->all())->toBe(['', 'en']);
});

it('says when a sitemap stayed the same', function (): void {
    app()->instance(SitePrefixes::class, new SitePrefixes(['']));
    Page::factory()->published()->at('/')->create();
    BuildSitemaps::run(new SitemapReason(SitemapCause::Command));

    $built = BuildSitemaps::run(new SitemapReason(SitemapCause::Command));

    expect($built[0]->changed)->toBeFalse();
});

it('builds nothing while no route group serves the pages', function (): void {
    app()->instance(SitePrefixes::class, new SitePrefixes([]));
    Page::factory()->published()->at('/')->create();

    expect(BuildSitemaps::run(new SitemapReason(SitemapCause::Command)))->toBe([])
        ->and(Sitemap::query()->count())->toBe(0);
});
