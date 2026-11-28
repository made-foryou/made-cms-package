<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Content\Actions\IgnoreNotFoundPath;
use Made\CmsPackage\Content\Actions\RestoreNotFoundPath;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Enums\HitOutcome;
use Made\CmsPackage\Content\Enums\VisitSource;
use Made\CmsPackage\Content\Events\NotFoundPathIgnored;
use Made\CmsPackage\Content\Events\NotFoundPathRestored;
use Made\CmsPackage\Content\Models\AddressHit;
use Made\CmsPackage\Content\Models\NotFoundPath;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-02 10:00:00'));
});

it('ignores a dead door, which leaves the list', function (): void {
    Event::fake([NotFoundPathIgnored::class]);
    $path = NotFoundPath::factory()->at('/wp-admin')->create();

    $ignored = IgnoreNotFoundPath::run($path);

    expect($ignored->ignored_at?->toDateTimeString())->toBe('2026-10-02 10:00:00')
        ->and(NotFoundPath::query()->listed(CarbonImmutable::parse('2026-09-01'))->exists())->toBeFalse();
    Event::assertDispatched(NotFoundPathIgnored::class, fn (NotFoundPathIgnored $event): bool => $event->path->is($path));
});

it('keeps the moment an address was first ignored', function (): void {
    Event::fake([NotFoundPathIgnored::class]);
    $path = NotFoundPath::factory()->at('/wp-admin')->ignoredAt(CarbonImmutable::parse('2026-09-14 08:00:00'))->create();

    expect(IgnoreNotFoundPath::run($path)->ignored_at?->toDateTimeString())->toBe('2026-09-14 08:00:00');
    Event::assertNotDispatched(NotFoundPathIgnored::class);
});

it('restores an ignored address to the list, with the visits it got in the meantime', function (): void {
    Event::fake([NotFoundPathRestored::class]);
    $path = NotFoundPath::factory()->at('/wp-admin')->ignoredAt(CarbonImmutable::parse('2026-09-14 08:00:00'))->create();
    AddressHit::factory()->at('/wp-admin')->outcome(HitOutcome::NotFound)->from(VisitSource::Bot)->hits(19)->create();

    $restored = RestoreNotFoundPath::run($path);

    expect($restored->ignored_at)->toBeNull()
        ->and(NotFoundPath::query()->listed(CarbonImmutable::parse('2026-09-01'))->pluck('path')->all())->toBe(['/wp-admin'])
        ->and(AddressHit::query()->at(PagePath::fromString('/wp-admin'))->totalHits())->toBe(19);
    Event::assertDispatched(NotFoundPathRestored::class, fn (NotFoundPathRestored $event): bool => $event->path->is($path));
});

it('restores nothing that is not ignored', function (): void {
    Event::fake([NotFoundPathRestored::class]);
    $path = NotFoundPath::factory()->at('/lid-worden-oud')->create();

    expect(RestoreNotFoundPath::run($path)->ignored_at)->toBeNull();
    Event::assertNotDispatched(NotFoundPathRestored::class);
});
