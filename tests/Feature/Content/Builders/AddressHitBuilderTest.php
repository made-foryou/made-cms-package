<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Enums\HitOutcome;
use Made\CmsPackage\Content\Enums\VisitSource;
use Made\CmsPackage\Content\Models\AddressHit;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-28 10:00:00'));
});

it('says how often people found a dead door in 30 days, and where they came from', function (): void {
    $deadDoor = AddressHit::factory()->at('/lid-worden-oud')->outcome(HitOutcome::NotFound);

    $deadDoor->daysAgo(2)->from(VisitSource::Google)->hits(5)->create();
    $deadDoor->daysAgo(10)->from(VisitSource::Google)->hits(4)->create();
    $deadDoor->daysAgo(3)->from(VisitSource::Internal, '/trainingstijden')->hits(3)->create();
    $deadDoor->daysAgo(1)->from(VisitSource::Direct)->hits(2)->create();
    $deadDoor->daysAgo(1)->from(VisitSource::Bot)->hits(31)->create();
    $deadDoor->daysAgo(40)->from(VisitSource::Google)->hits(7)->create();
    AddressHit::factory()->at('/contact.html')->from(VisitSource::Google)->hits(3)->create();

    $hits = AddressHit::query()
        ->at(PagePath::fromString('/lid-worden-oud'))
        ->outcome(HitOutcome::NotFound)
        ->fromPeople()
        ->since(CarbonImmutable::today()->subDays(30));

    expect($hits->totalHits())->toBe(14)
        ->and($hits->hitsPerSource())->toBe(['google' => 9, 'internal' => 3, 'direct' => 2])
        ->and($hits->hitsPerReferrer())->toBe(['/trainingstijden' => 3]);
});

it('says how often a redirect was used in 30 days and when last', function (): void {
    $redirect = AddressHit::factory()->at('/zomeractie')->outcome(HitOutcome::Redirected);

    $redirect->daysAgo(5)->from(VisitSource::Direct)->hits(30)->create();
    $redirect->daysAgo(1)->from(VisitSource::External)->hits(10)->create();
    $redirect->daysAgo(1)->from(VisitSource::Bot)->hits(1)->create();
    $redirect->daysAgo(31)->from(VisitSource::Direct)->hits(9)->create();

    $hits = AddressHit::query()->at(PagePath::fromString('/zomeractie'))->outcome(HitOutcome::Redirected)->since(CarbonImmutable::today()->subDays(30));

    expect($hits->totalHits())->toBe(41)
        ->and($hits->lastDay()?->toDateString())->toBe('2026-11-27');
});

it('says when Googlebot last fetched the sitemap of a route group', function (): void {
    $sitemap = AddressHit::factory()->outcome(HitOutcome::Served);

    $sitemap->at('/sitemap.xml')->daysAgo(3)->from(VisitSource::Googlebot)->create();
    $sitemap->at('/sitemap.xml')->daysAgo(9)->from(VisitSource::Googlebot)->create();
    $sitemap->at('/sitemap.xml')->daysAgo(0)->from(VisitSource::Bot)->create();
    $sitemap->at('/sitemap.xml', 'nl')->daysAgo(1)->from(VisitSource::Googlebot)->create();
    $sitemap->at('/robots.txt')->daysAgo(0)->from(VisitSource::Googlebot)->create();

    $fetched = fn (string $prefix): ?string => AddressHit::query()
        ->at(PagePath::fromString('/sitemap.xml'), $prefix)
        ->outcome(HitOutcome::Served)
        ->from(VisitSource::Googlebot)
        ->lastDay()
        ?->toDateString();

    expect($fetched(''))->toBe('2026-11-25')
        ->and($fetched('nl'))->toBe('2026-11-27')
        ->and($fetched('en'))->toBeNull();
});

it('says how often Googlebot fetched pages', function (): void {
    $page = AddressHit::factory()->outcome(HitOutcome::Page);

    $page->at('/')->from(VisitSource::Googlebot)->hits(30)->create();
    $page->at('/teams')->daysAgo(4)->from(VisitSource::Googlebot)->hits(11)->create();
    $page->at('/teams')->from(VisitSource::Bot)->hits(10)->create();

    expect(AddressHit::query()->outcome(HitOutcome::Page)->from(VisitSource::Googlebot)->since(CarbonImmutable::today()->subDays(30))->totalHits())->toBe(41)
        ->and(AddressHit::query()->outcome(HitOutcome::Page)->fromCrawlers()->totalHits())->toBe(51);
});

it('says when Googlebot last looked at a page, in any route group', function (): void {
    $page = AddressHit::factory()->outcome(HitOutcome::Page)->from(VisitSource::Googlebot);

    $page->at('/teams')->daysAgo(3)->create();
    $page->at('/teams')->daysAgo(10)->create();
    $page->at('/contact')->daysAgo(1)->create();

    expect(AddressHit::query()->at(PagePath::fromString('/teams'))->outcome(HitOutcome::Page)->from(VisitSource::Googlebot)->lastDay()?->toDateString())
        ->toBe('2026-11-25');
});

it('says how often a redirect was used this month', function (): void {
    $redirect = AddressHit::factory()->at('/teams-overzicht')->outcome(HitOutcome::Redirected);

    $redirect->on(CarbonImmutable::parse('2026-11-03'))->hits(2)->create();
    $redirect->on(CarbonImmutable::parse('2026-10-31'))->hits(5)->create();

    expect(AddressHit::query()->at(PagePath::fromString('/teams-overzicht'))->outcome(HitOutcome::Redirected)->since(CarbonImmutable::today()->startOfMonth())->totalHits())
        ->toBe(2);
});

it('says how often people found a dead door this week, and where most', function (): void {
    $deadDoor = AddressHit::factory()->outcome(HitOutcome::NotFound);

    $deadDoor->at('/lid-worden-oud')->from(VisitSource::Google)->hits(9)->create();
    $deadDoor->at('/contact.html')->daysAgo(2)->from(VisitSource::Direct)->hits(5)->create();
    $deadDoor->at('/wp-admin')->from(VisitSource::Bot)->hits(31)->create();
    $deadDoor->at('/nieuws-oud')->daysAgo(6)->from(VisitSource::Direct)->hits(20)->create();

    $hits = AddressHit::query()->outcome(HitOutcome::NotFound)->fromPeople()->since(CarbonImmutable::today()->startOfWeek());

    expect($hits->totalHits())->toBe(14)
        ->and($hits->hitsPerPath())->toBe(['/lid-worden-oud' => 9, '/contact.html' => 5]);
});

it('says how often an ignored address was asked for since it was ignored', function (): void {
    $deadDoor = AddressHit::factory()->at('/wp-admin')->outcome(HitOutcome::NotFound)->from(VisitSource::Bot);

    $deadDoor->on(CarbonImmutable::parse('2026-10-02'))->hits(4)->create();
    $deadDoor->on(CarbonImmutable::parse('2026-11-20'))->hits(15)->create();
    $deadDoor->on(CarbonImmutable::parse('2026-10-01'))->hits(12)->create();

    expect(AddressHit::query()->at(PagePath::fromString('/wp-admin'))->outcome(HitOutcome::NotFound)->since(CarbonImmutable::parse('2026-10-02 10:00:00'))->totalHits())
        ->toBe(19);
});

it('counts nothing and knows no day when nothing was counted', function (): void {
    $hits = AddressHit::query()->at(PagePath::fromString('/stil'));

    expect($hits->totalHits())->toBe(0)
        ->and($hits->hitsPerSource())->toBe([])
        ->and($hits->hitsPerReferrer())->toBe([])
        ->and($hits->hitsPerPath())->toBe([])
        ->and($hits->lastDay())->toBeNull();
});

it('finds the counts before a day, to clean them up', function (): void {
    AddressHit::factory()->daysAgo(91)->create();
    $kept = AddressHit::factory()->daysAgo(90)->create();

    expect(AddressHit::query()->before(CarbonImmutable::today()->subDays(90))->count())->toBe(1)
        ->and(AddressHit::query()->since(CarbonImmutable::today()->subDays(90))->pluck('id')->all())->toBe([$kept->id]);
});
