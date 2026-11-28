<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Builders\AddressHitBuilder;
use Made\CmsPackage\Content\Enums\HitOutcome;
use Made\CmsPackage\Content\Enums\VisitSource;
use Made\CmsPackage\Content\Models\AddressHit;

it('uses the custom builder for the counts of the addresses', function (): void {
    expect(AddressHit::query())->toBeInstanceOf(AddressHitBuilder::class);
});

it('reads a stored count in its own types', function (): void {
    $hit = AddressHit::factory()->create([
        'day' => CarbonImmutable::parse('2026-11-25'),
        'prefix' => 'nl',
        'path' => '/lid-worden-oud',
        'outcome' => HitOutcome::NotFound,
        'source' => VisitSource::Internal,
        'referrer_path' => '/trainingstijden',
        'hits' => 3,
    ]);

    $stored = AddressHit::query()->findOrFail($hit->id);

    expect($stored->day)->toBeInstanceOf(CarbonImmutable::class)
        ->and($stored->day->toDateString())->toBe('2026-11-25')
        ->and($stored->prefix)->toBe('nl')
        ->and($stored->path)->toBe('/lid-worden-oud')
        ->and($stored->outcome)->toBe(HitOutcome::NotFound)
        ->and($stored->source)->toBe(VisitSource::Internal)
        ->and($stored->referrer_path)->toBe('/trainingstijden')
        ->and($stored->hits)->toBe(3);
});

it('stores the day as a date without a time', function (): void {
    $hit = AddressHit::factory()->create(['day' => CarbonImmutable::parse('2026-11-25 14:30:00')]);

    expect(AddressHit::query()->whereKey($hit->id)->toBase()->value('day'))->toBe('2026-11-25')
        ->and(AddressHit::query()->where('day', '2026-11-25')->exists())->toBeTrue();
});
