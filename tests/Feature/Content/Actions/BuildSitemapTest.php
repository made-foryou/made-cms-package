<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\BuildSitemap;
use Made\CmsPackage\Content\Data\SitemapCandidate;
use Made\CmsPackage\Content\Data\SitemapReason;
use Made\CmsPackage\Content\Data\SitemapSources;
use Made\CmsPackage\Content\Enums\SitemapCause;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Sitemap;
use Made\CmsPackage\Content\Sitemap\SitemapSource;
use Made\CmsPackage\Content\Sitemap\SitemapXml;

beforeEach(function (): void {
    config(['app.url' => 'https://ttvmerwestad.nl', 'made-cms-package.sitemap.robots' => []]);
});

function buildSitemap(string $prefix = '', SitemapCause $cause = SitemapCause::Command): Sitemap
{
    return BuildSitemap::run($prefix, new SitemapReason($cause));
}

/**
 * A source that gives the addresses it is handed, for the limits and the order of the sources.
 *
 * @param  iterable<SitemapCandidate>  $candidates
 */
function sitemapSource(iterable $candidates): SitemapSource
{
    return new readonly class($candidates) implements SitemapSource
    {
        /**
         * @param  iterable<SitemapCandidate>  $candidates
         */
        public function __construct(private iterable $candidates) {}

        public function candidates(): iterable
        {
            return $this->candidates;
        }
    };
}

it('lists every published page visitors see and Google may show, with the moment it was last published', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-06 11:20:00'));
    Page::factory()->published()->at('/')->create(['title' => 'Home']);
    $this->travelTo(CarbonImmutable::parse('2026-11-07 09:00:00'));
    Page::factory()->published()->at('/teams')->create(['title' => 'Teams']);

    $sitemap = buildSitemap();

    expect(SitemapXml::locations($sitemap->xml))->toBe(['https://ttvmerwestad.nl/', 'https://ttvmerwestad.nl/teams'])
        ->and($sitemap->xml)->toContain('<lastmod>2026-11-06T11:20:00+00:00</lastmod>')
        ->and($sitemap->xml)->toContain('<lastmod>2026-11-07T09:00:00+00:00</lastmod>')
        ->and($sitemap->address_count)->toBe(2)
        ->and($sitemap->errors)->toBe([]);
});

it('writes the moment in the time zone of the site', function (): void {
    config(['app.timezone' => 'Europe/Amsterdam']);
    date_default_timezone_set('Europe/Amsterdam');
    $this->travelTo(CarbonImmutable::parse('2026-11-06 11:20:00', 'Europe/Amsterdam'));
    Page::factory()->published()->at('/')->create();

    try {
        expect(buildSitemap()->xml)->toContain('<lastmod>2026-11-06T11:20:00+01:00</lastmod>');
    } finally {
        date_default_timezone_set('UTC');
    }
});

it('leaves out what visitors or Google may not see', function (): void {
    Page::factory()->published()->at('/teams')->create();
    Page::factory()->at('/concept')->create();
    Page::factory()->published()->hidden()->at('/privacy')->create();
    Page::factory()->published()->noindex()->at('/zoeken')->create();
    Page::factory()->published()->outOfSitemap()->at('/bedankt')->create();
    Page::factory()->published()->deletedAt(now())->create();

    $sitemap = buildSitemap();

    expect(SitemapXml::locations($sitemap->xml))->toBe(['https://ttvmerwestad.nl/teams'])
        ->and($sitemap->errors)->toBe([]);
});

it('keeps a page under a hidden page, because Google may show it', function (): void {
    $privacy = Page::factory()->published()->hidden()->at('/privacy')->create();
    Page::factory()->published()->under($privacy)->at('/privacy/cookies')->create();

    expect(SitemapXml::locations(buildSitemap()->xml))->toBe(['https://ttvmerwestad.nl/privacy/cookies']);
});

it('orders the addresses by their path', function (): void {
    Page::factory()->published()->at('/teams')->create();
    Page::factory()->published()->at('/over-ons/team')->create();
    Page::factory()->published()->at('/')->create();
    Page::factory()->published()->at('/over-ons')->create();

    expect(SitemapXml::locations(buildSitemap()->xml))->toBe([
        'https://ttvmerwestad.nl/',
        'https://ttvmerwestad.nl/over-ons',
        'https://ttvmerwestad.nl/over-ons/team',
        'https://ttvmerwestad.nl/teams',
    ]);
});

it('counts a page without an address as an error', function (): void {
    Page::factory()->published()->create(['title' => 'Zwerver']);

    $sitemap = buildSitemap();

    expect($sitemap->address_count)->toBe(0)
        ->and($sitemap->errors)->toBe([['path' => null, 'label' => 'Zwerver', 'reason' => 'no_address']]);
});

it('counts a page a rule of robots.txt keeps Google away from as an error', function (): void {
    config(['made-cms-package.sitemap.robots' => [
        '*' => ['/api/'],
        'googlebot' => ['/zoeken$'],
        'GPTBot' => ['/'],
        'Bingbot' => ['/teams'],
    ]]);
    Page::factory()->published()->at('/api/docs')->create(['title' => 'Docs']);
    Page::factory()->published()->at('/zoeken')->create(['title' => 'Zoeken']);
    Page::factory()->published()->at('/zoeken/oud')->create(['title' => 'Oud']);
    Page::factory()->published()->at('/teams')->create(['title' => 'Teams']);

    $sitemap = buildSitemap();

    expect(SitemapXml::locations($sitemap->xml))->toBe(['https://ttvmerwestad.nl/teams', 'https://ttvmerwestad.nl/zoeken/oud'])
        ->and($sitemap->errors)->toBe([
            ['path' => '/api/docs', 'label' => 'Docs', 'reason' => 'blocked'],
            ['path' => '/zoeken', 'label' => 'Zoeken', 'reason' => 'blocked'],
        ]);
});

it('gives the addresses of a route group with a prefix, as its canonical addresses', function (): void {
    config(['made-cms-package.sitemap.robots' => ['*' => ['/nl/zoeken']]]);
    Page::factory()->published()->at('/')->create();
    Page::factory()->published()->at('/over-ons')->create();
    Page::factory()->published()->at('/zoeken')->create(['title' => 'Zoeken']);

    $sitemap = buildSitemap('nl');

    expect($sitemap->prefix)->toBe('nl')
        ->and(SitemapXml::locations($sitemap->xml))->toBe(['https://ttvmerwestad.nl/nl', 'https://ttvmerwestad.nl/nl/over-ons'])
        ->and($sitemap->errors)->toBe([['path' => '/nl/zoeken', 'label' => 'Zoeken', 'reason' => 'blocked']]);
});

it('stores what led to it and when', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-06 11:20:00'));
    $home = Page::factory()->published()->at('/')->create();
    $editor = Editor::factory()->create();

    $published = BuildSitemap::run('', new SitemapReason(SitemapCause::Published, pageId: $home->id));
    Page::factory()->published()->at('/teams')->create();
    $manual = BuildSitemap::run('', new SitemapReason(SitemapCause::Manual, editorId: $editor->id));

    expect($published->refresh()->cause)->toBe(SitemapCause::Published)
        ->and($published->page_id)->toBe($home->id)
        ->and($published->editor_id)->toBeNull()
        ->and($published->built_at->toDateTimeString())->toBe('2026-11-06 11:20:00')
        ->and($manual->refresh()->cause)->toBe(SitemapCause::Manual)
        ->and($manual->page_id)->toBeNull()
        ->and($manual->editor_id)->toBe($editor->id);
});

it('writes nothing new when nothing changed, so the moment of the sitemap stays the moment it last changed', function (): void {
    Page::factory()->published()->at('/')->create();
    $first = buildSitemap(cause: SitemapCause::Published);

    $this->travel(1)->hour();
    $again = buildSitemap(cause: SitemapCause::Meta);

    expect($again->is($first))->toBeTrue()
        ->and($again->cause)->toBe(SitemapCause::Published)
        ->and(Sitemap::query()->count())->toBe(1);
});

it('writes a new sitemap when only an error changed', function (): void {
    Page::factory()->published()->at('/')->create();
    buildSitemap();
    Page::factory()->published()->create(['title' => 'Zwerver']);

    buildSitemap();

    expect(Sitemap::query()->count())->toBe(2);
});

it('keeps the last ten sitemaps of a route group', function (): void {
    $other = Sitemap::factory()->create(['prefix' => 'nl', 'built_at' => now()->subYear()]);
    Sitemap::factory()->count(10)->create(['built_at' => now()->subDay()]);
    Page::factory()->published()->at('/')->create();

    $newest = buildSitemap();

    expect(Sitemap::query()->forPrefix('')->count())->toBe(BuildSitemap::KEEP)
        ->and(Sitemap::query()->forPrefix('')->newestFirst()->first()?->is($newest))->toBeTrue()
        ->and($other->fresh())->not->toBeNull();
});

it('takes the addresses of every source in their order', function (): void {
    app()->instance(SitemapSources::class, new SitemapSources([
        sitemapSource([new SitemapCandidate('Nieuws', '/nieuws/een', null)]),
        sitemapSource([new SitemapCandidate('Team', '/teams/a', null), new SitemapCandidate('Kwijt', null, null)]),
    ]));

    $sitemap = buildSitemap();

    expect(SitemapXml::locations($sitemap->xml))->toBe(['https://ttvmerwestad.nl/nieuws/een', 'https://ttvmerwestad.nl/teams/a'])
        ->and($sitemap->errors)->toBe([['path' => null, 'label' => 'Kwijt', 'reason' => 'no_address']]);
});

it('stops at the most addresses Google reads in one sitemap and counts the rest as errors', function (int $total, int $written, array $errors): void {
    $candidates = (function () use ($total): Generator {
        for ($number = 1; $number <= $total; $number++) {
            yield new SitemapCandidate('Pagina '.$number, '/p/'.$number, null);
        }
    })();
    app()->instance(SitemapSources::class, new SitemapSources([sitemapSource($candidates)]));

    $sitemap = buildSitemap();

    expect($sitemap->address_count)->toBe($written)
        ->and(substr_count($sitemap->xml, '<url>'))->toBe($written)
        ->and($sitemap->errors)->toBe($errors);
})->with([
    'at the limit' => [50_000, 50_000, []],
    'over the limit' => [50_001, 50_000, [['path' => '/p/50001', 'label' => 'Pagina 50001', 'reason' => 'full']]],
]);
