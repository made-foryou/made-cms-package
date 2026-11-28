<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Actions\PruneAddressHits;
use Made\CmsPackage\Content\Models\AddressHit;
use Made\CmsPackage\Content\Models\NotFoundPath;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-28 10:00:00'));
});

it('removes the counts older than the kept days and says how many', function (): void {
    AddressHit::factory()->daysAgo(91)->create();
    AddressHit::factory()->daysAgo(120)->at('/ander-adres')->create();
    $kept = AddressHit::factory()->daysAgo(90)->create();

    $pruned = PruneAddressHits::run();

    expect($pruned->hits)->toBe(2)
        ->and(AddressHit::query()->pluck('id')->all())->toBe([$kept->id]);
});

it('forgets a dead door no one tried within the kept days, but keeps one that is ignored', function (): void {
    NotFoundPath::factory()->at('/oud')->seen(CarbonImmutable::parse('2026-08-01 10:00:00'), CarbonImmutable::parse('2026-08-29 23:59:59'))->create();
    NotFoundPath::factory()->at('/net-op-tijd')->seen(CarbonImmutable::parse('2026-08-30 00:00:00'))->create();
    NotFoundPath::factory()->at('/wp-admin')->seen(CarbonImmutable::parse('2026-05-01 10:00:00'))->ignoredAt(CarbonImmutable::parse('2026-05-02 10:00:00'))->create();

    $pruned = PruneAddressHits::run();

    expect($pruned->paths)->toBe(1)
        ->and(NotFoundPath::query()->orderBy('path')->pluck('path')->all())->toBe(['/net-op-tijd', '/wp-admin']);
});

it('follows the kept days of config', function (): void {
    config(['made-cms-package.address_hits.keep_days' => 45]);
    AddressHit::factory()->daysAgo(46)->create();
    AddressHit::factory()->daysAgo(45)->at('/ander-adres')->create();

    expect(PruneAddressHits::run()->hits)->toBe(1)
        ->and(AddressHit::query()->pluck('path')->all())->toBe(['/ander-adres']);
});

it('keeps at least the 30 days the screens look back on', function (): void {
    AddressHit::factory()->daysAgo(31)->create();
    AddressHit::factory()->daysAgo(30)->at('/ander-adres')->create();

    expect(PruneAddressHits::run(1)->hits)->toBe(1)
        ->and(AddressHit::query()->pluck('path')->all())->toBe(['/ander-adres']);
});
