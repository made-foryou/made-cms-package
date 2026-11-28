<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Actions\RecordAddressHit;
use Made\CmsPackage\Content\Data\AddressVisit;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Data\VisitClues;
use Made\CmsPackage\Content\Enums\HitOutcome;
use Made\CmsPackage\Content\Models\AddressHit;
use Made\CmsPackage\Content\Models\NotFoundPath;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-28 10:00:00'));
});

function visitOf(
    string $path,
    HitOutcome $outcome = HitOutcome::NotFound,
    ?string $userAgent = null,
    ?string $referrer = null,
    string $prefix = '',
    bool $editing = false,
): AddressVisit {
    return new AddressVisit(
        $prefix,
        PagePath::fromString($path),
        $outcome,
        new VisitClues($userAgent ?? aBrowser(), $referrer, ['site.test']),
        $editing,
    );
}

/**
 * @return list<array{day: string, prefix: string, path: string, outcome: string, source: string, referrer_path: string, hits: int}>
 */
function storedHits(): array
{
    return AddressHit::query()->orderBy('id')->get()->map(static fn (AddressHit $hit): array => [
        'day' => $hit->day->toDateString(),
        'prefix' => $hit->prefix,
        'path' => $hit->path,
        'outcome' => $hit->outcome->value,
        'source' => $hit->source->value,
        'referrer_path' => $hit->referrer_path,
        'hits' => $hit->hits,
    ])->all();
}

it('counts a visit of an address without a page', function (): void {
    expect(RecordAddressHit::run(visitOf('/Lid-Worden-Oud/')))->toBeTrue()
        ->and(storedHits())->toBe([[
            'day' => '2026-11-28',
            'prefix' => '',
            'path' => '/lid-worden-oud',
            'outcome' => 'not_found',
            'source' => 'direct',
            'referrer_path' => '',
            'hits' => 1,
        ]]);
});

it('counts the visits of a day in one row', function (): void {
    RecordAddressHit::run(visitOf('/lid-worden-oud'));
    RecordAddressHit::run(visitOf('/lid-worden-oud'));
    RecordAddressHit::run(visitOf('/lid-worden-oud'));

    expect(storedHits())->toHaveCount(1)
        ->and(storedHits()[0]['hits'])->toBe(3);
});

it('counts each source, outcome, group and day apart', function (): void {
    RecordAddressHit::run(visitOf('/lid-worden-oud'));
    RecordAddressHit::run(visitOf('/lid-worden-oud', referrer: 'https://www.google.nl/'));
    RecordAddressHit::run(visitOf('/lid-worden-oud', prefix: 'nl'));
    RecordAddressHit::run(visitOf('/sitemap.xml', HitOutcome::Served, googlebot()));

    $this->travelTo(CarbonImmutable::parse('2026-11-29 10:00:00'));
    RecordAddressHit::run(visitOf('/lid-worden-oud'));

    expect(collect(storedHits())->map(fn (array $hit): string => implode(' ', [$hit['day'], $hit['prefix'] ?: '-', $hit['path'], $hit['outcome'], $hit['source'], $hit['hits']]))->all())
        ->toBe([
            '2026-11-28 - /lid-worden-oud not_found direct 1',
            '2026-11-28 - /lid-worden-oud not_found google 1',
            '2026-11-28 nl /lid-worden-oud not_found direct 1',
            '2026-11-28 - /sitemap.xml served googlebot 1',
            '2026-11-29 - /lid-worden-oud not_found direct 1',
        ]);
});

it('counts the day in the timezone of the app', function (): void {
    config(['app.timezone' => 'Europe/Amsterdam']);
    date_default_timezone_set('Europe/Amsterdam');

    try {
        $this->travelTo(CarbonImmutable::parse('2026-11-28 23:30:00', 'UTC'));

        RecordAddressHit::run(visitOf('/lid-worden-oud'));

        expect(storedHits()[0]['day'])->toBe('2026-11-29');
    } finally {
        date_default_timezone_set('UTC');
    }
});

it('counts nothing for an editor in the edit mode or the preview', function (): void {
    expect(RecordAddressHit::run(visitOf('/concept', editing: true)))->toBeFalse()
        ->and(AddressHit::query()->exists())->toBeFalse()
        ->and(NotFoundPath::query()->exists())->toBeFalse();
});

it('counts a page for a crawler only', function (): void {
    expect(RecordAddressHit::run(visitOf('/teams', HitOutcome::Page)))->toBeFalse()
        ->and(RecordAddressHit::run(visitOf('/teams', HitOutcome::Page, googlebot())))->toBeTrue()
        ->and(RecordAddressHit::run(visitOf('/teams', HitOutcome::Page, 'curl/8.7.1')))->toBeTrue()
        ->and(collect(storedHits())->pluck('source')->all())->toBe(['googlebot', 'bot']);
});

it('counts the files and redirects for people too', function (HitOutcome $outcome): void {
    expect(RecordAddressHit::run(visitOf('/robots.txt', $outcome)))->toBeTrue()
        ->and(storedHits()[0]['source'])->toBe('direct');
})->with([HitOutcome::Served, HitOutcome::Redirected]);

it('notes when an address without a page was first and last seen', function (): void {
    RecordAddressHit::run(visitOf('/wp-admin', userAgent: 'curl/8.7.1'));

    $this->travelTo(CarbonImmutable::parse('2026-11-30 08:15:00'));
    RecordAddressHit::run(visitOf('/wp-admin', userAgent: 'curl/8.7.1'));

    $path = NotFoundPath::query()->sole();

    expect($path->path)->toBe('/wp-admin')
        ->and($path->first_seen_at->toDateTimeString())->toBe('2026-11-28 10:00:00')
        ->and($path->last_seen_at->toDateTimeString())->toBe('2026-11-30 08:15:00')
        ->and($path->ignored_at)->toBeNull();
});

it('notes no address for a file or a page', function (): void {
    RecordAddressHit::run(visitOf('/sitemap.xml', HitOutcome::Served));
    RecordAddressHit::run(visitOf('/teams', HitOutcome::Page, googlebot()));

    expect(NotFoundPath::query()->exists())->toBeFalse();
});

it('keeps counting an address that is ignored', function (): void {
    NotFoundPath::factory()->at('/wp-admin')->ignoredAt(CarbonImmutable::parse('2026-10-02 10:00:00'))->create();

    expect(RecordAddressHit::run(visitOf('/wp-admin', userAgent: 'curl/8.7.1')))->toBeTrue()
        ->and(NotFoundPath::query()->sole()->ignored_at?->toDateString())->toBe('2026-10-02');
});

it('takes no more new addresses in a day than config allows, but keeps counting the ones it knows', function (): void {
    config(['made-cms-package.address_hits.new_paths_per_day' => 2]);
    NotFoundPath::factory()->at('/gisteren')->seen(CarbonImmutable::parse('2026-11-27 12:00:00'))->create();

    expect(RecordAddressHit::run(visitOf('/een')))->toBeTrue()
        ->and(RecordAddressHit::run(visitOf('/twee')))->toBeTrue()
        ->and(RecordAddressHit::run(visitOf('/drie')))->toBeFalse()
        ->and(RecordAddressHit::run(visitOf('/een')))->toBeTrue()
        ->and(RecordAddressHit::run(visitOf('/gisteren')))->toBeTrue()
        ->and(NotFoundPath::query()->orderBy('path')->pluck('path')->all())->toBe(['/een', '/gisteren', '/twee'])
        ->and(collect(storedHits())->pluck('hits', 'path')->all())->toBe(['/een' => 2, '/twee' => 1, '/gisteren' => 1]);

    $this->travelTo(CarbonImmutable::parse('2026-11-29 00:00:01'));

    expect(RecordAddressHit::run(visitOf('/drie')))->toBeTrue();
});

it('takes 100 new addresses a day without config', function (): void {
    NotFoundPath::factory()->count(100)->create();

    expect(RecordAddressHit::run(visitOf('/nummer-101')))->toBeFalse();
});

it('keeps the page of the site that linked here when it is an address of the site', function (): void {
    Page::factory()->published()->at('/trainingstijden')->create();

    RecordAddressHit::run(visitOf('/lid-worden-oud', referrer: 'https://site.test/Trainingstijden?week=48'));
    RecordAddressHit::run(visitOf('/lid-worden-oud', referrer: 'https://site.test/bestaat-niet'));

    expect(collect(storedHits())->map(fn (array $hit): string => $hit['source'].' '.$hit['referrer_path'].' '.$hit['hits'])->all())
        ->toBe(['internal /trainingstijden 1', 'internal  1']);
});

it('reads the page that linked here within the route group of the address', function (): void {
    Page::factory()->published()->at('/trainingstijden')->create();

    RecordAddressHit::run(visitOf('/lid-worden-oud', referrer: 'https://site.test/nl/trainingstijden', prefix: 'nl'));
    RecordAddressHit::run(visitOf('/lid-worden-oud', referrer: 'https://site.test/nl', prefix: 'nl'));

    expect(collect(storedHits())->pluck('referrer_path')->all())->toBe(['/trainingstijden', '']);
});

it('counts nothing for an address or a group longer than an address can be', function (): void {
    expect(RecordAddressHit::run(visitOf('/'.str_repeat('a', 255))))->toBeFalse()
        ->and(RecordAddressHit::run(visitOf('/kort', prefix: str_repeat('n', 65))))->toBeFalse()
        ->and(RecordAddressHit::run(visitOf('/'.str_repeat('a', 254))))->toBeTrue()
        ->and(AddressHit::query()->count())->toBe(1);
});

it('says only where a visit came from, never who made it', function (): void {
    RecordAddressHit::run(visitOf('/lid-worden-oud', referrer: 'https://www.google.nl/'));

    expect(json_encode(AddressHit::query()->toBase()->get()))->not->toContain('Mozilla')
        ->and(json_encode(NotFoundPath::query()->toBase()->get()))->not->toContain('Mozilla');
});
