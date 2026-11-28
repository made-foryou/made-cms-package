<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Actions\OpenSitemapOverview;
use Made\CmsPackage\Content\Data\SitemapSearch;
use Made\CmsPackage\Content\Data\SitePrefixes;
use Made\CmsPackage\Content\Enums\HitOutcome;
use Made\CmsPackage\Content\Enums\SitemapCause;
use Made\CmsPackage\Content\Enums\VisitSource;
use Made\CmsPackage\Content\Models\AddressHit;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Sitemap;

beforeEach(function (): void {
    config(['app.url' => 'https://ttvmerwestad.nl', 'made-cms-package.sitemap.robots' => []]);
    $this->travelTo(CarbonImmutable::parse('2026-11-28 10:00:00'));
    app()->instance(SitePrefixes::class, new SitePrefixes(['', 'nl']));
});

function sitemapFetched(int $daysAgo, string $prefix = ''): void
{
    AddressHit::factory()->at('/sitemap.xml', $prefix)->outcome(HitOutcome::Served)->from(VisitSource::Googlebot)->daysAgo($daysAgo)->create();
}

it('opens the sitemap of the first route group, with its pages', function (): void {
    Page::factory()->published()->at('/')->create(['title' => 'Home']);

    $overview = OpenSitemapOverview::run(new SitemapSearch(null));

    expect($overview->prefixes)->toBe(['', 'nl'])
        ->and($overview->prefix)->toBe('')
        ->and($overview->sitemap?->prefix)->toBe('')
        ->and($overview->sitemap?->address_count)->toBe(1)
        ->and($overview->rows->rows[0]->name)->toBe('Home')
        ->and($overview->files?->sitemap)->toBeNull();
});

it('opens the sitemap of the route group asked for, and of the first one for a group that is not there', function (): void {
    expect(OpenSitemapOverview::run(new SitemapSearch('nl'))->prefix)->toBe('nl')
        ->and(OpenSitemapOverview::run(new SitemapSearch('en'))->prefix)->toBe('');
});

it('opens without a sitemap when no route serves the pages', function (): void {
    app()->instance(SitePrefixes::class, new SitePrefixes([]));
    Page::factory()->published()->at('/')->create(['title' => 'Home']);

    $overview = OpenSitemapOverview::run(new SitemapSearch(null));

    expect($overview->prefixes)->toBe([])
        ->and($overview->sitemap)->toBeNull()
        ->and($overview->files)->toBeNull()
        ->and($overview->rows->rows[0]->name)->toBe('Home')
        ->and(Sitemap::query()->exists())->toBeFalse();
});

it('names the page whose change built the sitemap, also one that was deleted', function (): void {
    $page = Page::factory()->published()->at('/teams')->create(['title' => 'Onze teams']);
    Sitemap::factory()->create(['cause' => SitemapCause::Published, 'page_id' => $page->id]);

    expect(OpenSitemapOverview::run(new SitemapSearch(null))->causePage)->toBe('Onze teams');

    $page->route()->delete();
    $page->delete();

    expect(OpenSitemapOverview::run(new SitemapSearch(null))->causePage)->toBe('Onze teams');
});

it('says when Googlebot last fetched the sitemap and how many pages changed since that day', function (): void {
    sitemapFetched(3);
    sitemapFetched(9);
    sitemapFetched(0, 'nl');
    Page::factory()->published()->at('/')->create(['published_at' => CarbonImmutable::parse('2026-11-25 23:00:00')]);
    Page::factory()->published()->at('/teams')->create(['published_at' => CarbonImmutable::parse('2026-11-26 08:00:00')]);
    Page::factory()->published()->at('/contact')->create(['published_at' => CarbonImmutable::parse('2026-11-27 12:00:00')]);
    Page::factory()->published()->outOfSitemap()->at('/privacy')->create(['published_at' => CarbonImmutable::parse('2026-11-27 12:00:00')]);

    $fetches = OpenSitemapOverview::run(new SitemapSearch(null))->fetches;

    expect($fetches->lastFetch?->toDateString())->toBe('2026-11-25')
        ->and($fetches->changedSince)->toBe(2)
        ->and(OpenSitemapOverview::run(new SitemapSearch('nl'))->fetches->lastFetch?->toDateString())->toBe('2026-11-28');
});

it('says since when it counts and how often Googlebot came to the pages of the group when it never fetched the sitemap', function (): void {
    AddressHit::factory()->at('/teams')->outcome(HitOutcome::Page)->from(VisitSource::Googlebot)->daysAgo(20)->hits(30)->create();
    AddressHit::factory()->at('/')->outcome(HitOutcome::Page)->from(VisitSource::Googlebot)->daysAgo(2)->hits(11)->create();
    AddressHit::factory()->at('/teams', 'nl')->outcome(HitOutcome::Page)->from(VisitSource::Googlebot)->hits(5)->create();
    AddressHit::factory()->at('/teams')->outcome(HitOutcome::Page)->from(VisitSource::Bot)->hits(7)->create();
    sitemapFetched(1, 'nl');

    $fetches = OpenSitemapOverview::run(new SitemapSearch(null))->fetches;

    expect($fetches->lastFetch)->toBeNull()
        ->and($fetches->countingSince?->toDateString())->toBe('2026-11-08')
        ->and($fetches->googlebotPages)->toBe(41)
        ->and($fetches->changedSince)->toBe(0);
});

it('knows no day it counts from before it counted anything', function (): void {
    expect(OpenSitemapOverview::run(new SitemapSearch(null))->fetches->countingSince)->toBeNull();
});

it('hands over the files of the public folder that go before the sitemap', function (): void {
    $folder = usePublicFolder();
    file_put_contents($folder.'/robots.txt', "User-agent: *\nDisallow:\n");

    $files = OpenSitemapOverview::run(new SitemapSearch(null))->files;

    expect($files?->robots?->namesCmsSitemap)->toBeFalse()
        ->and($files?->sitemap)->toBeNull();
});
