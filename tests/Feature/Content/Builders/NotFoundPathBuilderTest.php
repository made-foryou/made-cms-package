<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Models\NotFoundPath;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-28 10:00:00'));
});

function listedPaths(CarbonImmutable $since): array
{
    return NotFoundPath::query()->listed($since)->orderBy('path')->pluck('path')->all();
}

it('lists the dead doors seen since a moment', function (): void {
    NotFoundPath::factory()->at('/lid-worden-oud')->seen(CarbonImmutable::parse('2026-10-01 09:00:00'), CarbonImmutable::parse('2026-11-27 18:00:00'))->create();
    NotFoundPath::factory()->at('/contact.html')->seen(CarbonImmutable::parse('2026-10-29 10:00:00'))->create();
    NotFoundPath::factory()->at('/heel-oud')->seen(CarbonImmutable::parse('2026-10-28 09:59:59'))->create();

    expect(listedPaths(CarbonImmutable::parse('2026-10-29 10:00:00')))->toBe(['/contact.html', '/lid-worden-oud']);
});

it('leaves an ignored address out of the list', function (): void {
    NotFoundPath::factory()->at('/wp-admin')->ignoredAt(CarbonImmutable::parse('2026-10-02 10:00:00'))->create();
    NotFoundPath::factory()->at('/lid-worden-oud')->create();

    expect(listedPaths(CarbonImmutable::today()->subDays(30)))->toBe(['/lid-worden-oud'])
        ->and(NotFoundPath::query()->ignored()->pluck('path')->all())->toBe(['/wp-admin']);
});

it('leaves an address out of the list once a page has it, also a draft', function (): void {
    NotFoundPath::factory()->at('/lid-worden')->create();
    NotFoundPath::factory()->at('/jeugd')->create();
    NotFoundPath::factory()->at('/lid-worden-oud')->create();
    Page::factory()->published()->at('/lid-worden')->create();
    Page::factory()->at('/jeugd')->create();

    expect(listedPaths(CarbonImmutable::today()->subDays(30)))->toBe(['/lid-worden-oud']);
});

it('lists an address again once its page is deleted', function (): void {
    NotFoundPath::factory()->at('/locatie')->create();
    $page = Page::factory()->published()->at('/locatie')->create();

    expect(listedPaths(CarbonImmutable::today()->subDays(30)))->toBe([]);

    $page->route()->delete();

    expect(listedPaths(CarbonImmutable::today()->subDays(30)))->toBe(['/locatie']);
});

it('finds an address by its path', function (): void {
    NotFoundPath::factory()->at('/wp-admin')->create();

    expect(NotFoundPath::query()->atPath(PagePath::fromString('/WP-Admin/'))->exists())->toBeTrue()
        ->and(NotFoundPath::query()->atPath(PagePath::fromString('/wp-login.php'))->exists())->toBeFalse();
});

it('finds the addresses that came in since a moment', function (): void {
    NotFoundPath::factory()->at('/gisteren')->seen(CarbonImmutable::parse('2026-11-27 23:59:59'))->create();
    NotFoundPath::factory()->at('/vandaag')->seen(CarbonImmutable::parse('2026-11-28 00:00:00'))->create();

    expect(NotFoundPath::query()->firstSeenSince(CarbonImmutable::today())->pluck('path')->all())->toBe(['/vandaag']);
});

it('finds the addresses no one asked for since a moment, and those that are not ignored', function (): void {
    NotFoundPath::factory()->at('/oud')->seen(CarbonImmutable::parse('2026-08-01 10:00:00'))->create();
    NotFoundPath::factory()->at('/oud-genegeerd')->seen(CarbonImmutable::parse('2026-08-01 10:00:00'))->ignoredAt(CarbonImmutable::parse('2026-08-02 10:00:00'))->create();
    NotFoundPath::factory()->at('/recent')->create();

    expect(NotFoundPath::query()->lastSeenBefore(CarbonImmutable::parse('2026-08-30 00:00:00'))->orderBy('path')->pluck('path')->all())->toBe(['/oud', '/oud-genegeerd'])
        ->and(NotFoundPath::query()->notIgnored()->orderBy('path')->pluck('path')->all())->toBe(['/oud', '/recent']);
});
