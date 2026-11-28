<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Made\CmsPackage\Content\Actions\BuildSitemap;
use Made\CmsPackage\Content\Actions\ListSitemapRows;
use Made\CmsPackage\Content\Data\SitemapReason;
use Made\CmsPackage\Content\Data\SitemapRow;
use Made\CmsPackage\Content\Enums\SitemapCause;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Sitemap\SitemapXml;

beforeEach(function (): void {
    config(['app.url' => 'https://ttvmerwestad.nl', 'made-cms-package.sitemap.robots' => ['*' => ['/zoeken']]]);
});

/**
 * Every row as "name depth standing".
 *
 * @param  list<SitemapRow>  $rows
 * @return list<string>
 */
function sitemapRowLines(array $rows): array
{
    return array_map(static fn (SitemapRow $row): string => $row->name.' '.$row->depth.' '.$row->standing->value, $rows);
}

function sitemapSite(): void
{
    Page::factory()->published()->at('/')->atPosition(0)->create(['title' => 'Home']);
    $teams = Page::factory()->published()->at('/teams')->atPosition(1)->create(['title' => 'Onze teams']);
    Page::factory()->published()->under($teams)->at('/teams/merwestad-1')->create(['title' => 'Merwestad 1']);
    Page::factory()->at('/jeugd')->atPosition(2)->create(['title' => 'Jeugd']);
    Page::factory()->published()->at('/zoeken')->atPosition(3)->create(['title' => 'Zoeken']);
    Page::factory()->published()->noindex()->at('/proefles')->atPosition(4)->create(['title' => 'Proefles aanvragen']);
    Page::factory()->published()->hidden()->at('/bedankt')->atPosition(5)->create(['title' => 'Bedankt']);
    Page::factory()->published()->outOfSitemap()->at('/privacy')->atPosition(6)->create(['title' => 'Privacy']);
}

it('lists every page in menu order with its depth and whether it is in the sitemap, and why not', function (): void {
    sitemapSite();

    expect(sitemapRowLines(ListSitemapRows::run('')->rows))->toBe([
        'Home 0 in',
        'Onze teams 0 in',
        'Merwestad 1 1 in',
        'Jeugd 0 draft',
        'Zoeken 0 blocked',
        'Proefles aanvragen 0 noindex',
        'Bedankt 0 hidden',
        'Privacy 0 excluded',
    ]);
});

it('gives each row its address and the moment it was last published', function (): void {
    $published = CarbonImmutable::parse('2026-11-20 09:15:00');
    Page::factory()->published()->at('/teams')->create(['title' => 'Onze teams', 'published_at' => $published]);
    Page::factory()->at('/jeugd')->create(['title' => 'Jeugd']);

    $rows = ListSitemapRows::run('')->rows;

    expect($rows[0]->path?->value)->toBe('/teams')
        ->and($rows[0]->publishedAt?->toDateTimeString())->toBe('2026-11-20 09:15:00')
        ->and($rows[1]->publishedAt)->toBeNull();
});

it('reads robots.txt for the addresses of the route group', function (): void {
    config(['made-cms-package.sitemap.robots' => ['*' => ['/nl/teams']]]);
    Page::factory()->published()->at('/teams')->create(['title' => 'Onze teams']);

    expect(sitemapRowLines(ListSitemapRows::run('nl')->rows))->toBe(['Onze teams 0 blocked'])
        ->and(sitemapRowLines(ListSitemapRows::run('')->rows))->toBe(['Onze teams 0 in']);
});

it('names the pages that are in the sitemap and the ones kept out of it on purpose', function (): void {
    sitemapSite();

    $rows = ListSitemapRows::run('');

    expect(array_map(fn (SitemapRow $row): string => $row->name, $rows->in()))->toBe(['Home', 'Onze teams', 'Merwestad 1'])
        ->and(array_map(fn (SitemapRow $row): string => $row->name, $rows->deliberatelyOut()))->toBe(['Proefles aanvragen', 'Bedankt', 'Privacy'])
        ->and($rows->find(missingId()))->toBeNull();
});

it('says in the list what the sitemap holds', function (): void {
    sitemapSite();

    $built = BuildSitemap::run('', new SitemapReason(SitemapCause::Command));
    $listed = array_map(
        fn (SitemapRow $row): string => 'https://ttvmerwestad.nl'.$row->path?->value,
        ListSitemapRows::run('')->in(),
    );

    expect(SitemapXml::locations($built->xml))->toEqualCanonicalizing($listed);
});

it('reads the pages in the same number of queries however many there are', function (): void {
    sitemapSite();

    $queries = 0;
    DB::listen(static function (QueryExecuted $query) use (&$queries): void {
        $queries++;
    });

    ListSitemapRows::run('');
    $few = $queries;

    Page::factory()->published()->count(12)->sequence(fn ($sequence): array => ['position' => 10 + $sequence->index])->create();
    foreach (Page::query()->doesntHave('route')->get() as $page) {
        $page->route()->create(['path' => '/extra-'.$page->id]);
    }

    $queries = 0;
    ListSitemapRows::run('');

    expect($queries)->toBe($few);
});
