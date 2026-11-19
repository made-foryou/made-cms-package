<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\OpenSitemap;
use Made\CmsPackage\Content\Enums\SitemapCause;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Sitemap;

it('opens the newest sitemap of a route group', function (): void {
    Sitemap::factory()->create(['built_at' => now()->subDay()]);
    $newest = Sitemap::factory()->create(['built_at' => now()]);
    Sitemap::factory()->create(['prefix' => 'nl', 'built_at' => now()->addMinute()]);

    expect(OpenSitemap::run('')->is($newest))->toBeTrue();
});

it('builds the sitemap of a route group that has none yet', function (): void {
    config(['app.url' => 'https://ttvmerwestad.nl']);
    Page::factory()->published()->at('/')->create();

    $sitemap = OpenSitemap::run('nl');

    expect($sitemap->prefix)->toBe('nl')
        ->and($sitemap->cause)->toBe(SitemapCause::Requested)
        ->and($sitemap->xml)->toContain('<loc>https://ttvmerwestad.nl/nl</loc>');
});
