<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Models\Sitemap;

it('finds the sitemaps of one route group', function (): void {
    $root = Sitemap::factory()->create(['prefix' => '']);
    $dutch = Sitemap::factory()->create(['prefix' => 'nl']);

    expect(Sitemap::query()->forPrefix('')->pluck('id')->all())->toBe([$root->id])
        ->and(Sitemap::query()->forPrefix('nl')->pluck('id')->all())->toBe([$dutch->id]);
});

it('lists the newest sitemap first, by the moment it was built and then by its id', function (): void {
    $older = Sitemap::factory()->create(['built_at' => now()->subHour()]);
    $first = Sitemap::factory()->create(['built_at' => now()]);
    $second = Sitemap::factory()->create(['built_at' => now()]);

    expect(Sitemap::query()->newestFirst()->pluck('id')->all())->toBe([$second->id, $first->id, $older->id]);
});
