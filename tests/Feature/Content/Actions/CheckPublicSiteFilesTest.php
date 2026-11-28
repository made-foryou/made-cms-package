<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Actions\CheckPublicSiteFiles;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'https://www.ttvmerwestad.nl']);
});

it('finds nothing in the way when the public folder has neither file', function (): void {
    usePublicFolder();

    $files = CheckPublicSiteFiles::run();

    expect($files->sitemap)->toBeNull()
        ->and($files->robots)->toBeNull();
});

it('tells when a sitemap of the site goes first, how many addresses it has and how many pages it misses', function (): void {
    $folder = usePublicFolder();
    Page::factory()->published()->at('/')->create();
    Page::factory()->published()->at('/teams')->create();
    Page::factory()->published()->at('/lid-worden')->create();
    file_put_contents($folder.'/sitemap.xml', publicSitemap([
        'http://ttvmerwestad.nl/',
        'https://ttvmerwestad.nl/teams/',
        'https://ttvmerwestad.nl/oud-nieuws',
    ]));
    touch($folder.'/sitemap.xml', CarbonImmutable::parse('2024-03-02 10:00:00')->getTimestamp());

    $sitemap = CheckPublicSiteFiles::run()->sitemap;

    expect($sitemap?->modifiedAt->toDateTimeString())->toBe('2024-03-02 10:00:00')
        ->and($sitemap?->addressCount)->toBe(3)
        ->and($sitemap?->missingPages)->toBe(1);
});

it('looks for the sitemap of a route group under its prefix', function (): void {
    $folder = usePublicFolder();
    Page::factory()->published()->at('/')->create();
    file_put_contents($folder.'/nl/sitemap.xml', publicSitemap(['https://ttvmerwestad.nl/nl']));

    expect(CheckPublicSiteFiles::run()->sitemap)->toBeNull()
        ->and(CheckPublicSiteFiles::run('nl')->sitemap?->missingPages)->toBe(0);
});

it('cannot count the addresses of a sitemap that is no xml', function (): void {
    $folder = usePublicFolder();
    file_put_contents($folder.'/sitemap.xml', 'Hier komt de sitemap');

    $sitemap = CheckPublicSiteFiles::run()->sitemap;

    expect($sitemap)->not->toBeNull()
        ->and($sitemap?->addressCount)->toBeNull()
        ->and($sitemap?->missingPages)->toBeNull();
});

it('tells whether a robots.txt of the site names the sitemap of the cms', function (string $robots, bool $names): void {
    $folder = usePublicFolder();
    file_put_contents($folder.'/robots.txt', $robots);

    expect(CheckPublicSiteFiles::run()->robots?->namesCmsSitemap)->toBe($names);
})->with([
    'no sitemap line' => ["User-agent: *\nDisallow: /admin/\n", false],
    'the sitemap of the cms' => ["User-agent: *\nDisallow:\n\nSitemap: https://www.ttvmerwestad.nl/sitemap.xml\n", true],
    'without www and in other capitals' => ["user-agent: *\nSITEMAP:   https://ttvmerwestad.nl/sitemap.xml\r\n", true],
    'another sitemap of the site' => ["Sitemap: https://ttvmerwestad.nl/sitemap_index.xml\n", false],
    'the sitemap of another site' => ["Sitemap: https://example.com/sitemap.xml\n", false],
    'a comment' => ["# Sitemap: https://ttvmerwestad.nl/sitemap.xml\n", false],
]);

it('tells whether robots.txt names the sitemap of a route group with a prefix', function (): void {
    $folder = usePublicFolder();
    file_put_contents($folder.'/robots.txt', "Sitemap: https://ttvmerwestad.nl/nl/sitemap.xml\n");

    expect(CheckPublicSiteFiles::run('nl')->robots?->namesCmsSitemap)->toBeTrue()
        ->and(CheckPublicSiteFiles::run()->robots?->namesCmsSitemap)->toBeFalse();
});
