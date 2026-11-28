<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Builders\NotFoundPathBuilder;
use Made\CmsPackage\Content\Models\NotFoundPath;

it('uses the custom builder for the addresses without a page', function (): void {
    expect(NotFoundPath::query())->toBeInstanceOf(NotFoundPathBuilder::class);
});

it('reads a stored address in its own types', function (): void {
    $path = NotFoundPath::factory()->create([
        'path' => '/wp-admin',
        'first_seen_at' => CarbonImmutable::parse('2026-09-30 08:00:00'),
        'last_seen_at' => CarbonImmutable::parse('2026-11-27 21:15:00'),
        'ignored_at' => CarbonImmutable::parse('2026-10-02 10:00:00'),
    ]);

    $stored = NotFoundPath::query()->findOrFail($path->id);

    expect($stored->path)->toBe('/wp-admin')
        ->and($stored->first_seen_at->toDateTimeString())->toBe('2026-09-30 08:00:00')
        ->and($stored->last_seen_at->toDateTimeString())->toBe('2026-11-27 21:15:00')
        ->and($stored->ignored_at?->toDateTimeString())->toBe('2026-10-02 10:00:00');
});

it('is not ignored until someone ignores it', function (): void {
    expect(NotFoundPath::factory()->create()->ignored_at)->toBeNull();
});
