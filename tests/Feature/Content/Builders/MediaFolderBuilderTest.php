<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Builders\MediaFolderBuilder;
use Made\CmsPackage\Content\Models\MediaFolder;

it('uses the custom builder for folder queries', function (): void {
    expect(MediaFolder::query())->toBeInstanceOf(MediaFolderBuilder::class);
});

it('lists the folders by name', function (): void {
    MediaFolder::factory()->create(['name' => 'Teams']);
    MediaFolder::factory()->create(['name' => 'Logo\'s']);
    MediaFolder::factory()->create(['name' => 'Nieuws']);

    expect(MediaFolder::query()->alphabetical()->pluck('name')->all())->toBe(['Logo\'s', 'Nieuws', 'Teams']);
});

it('finds a folder by its name without regard to capitals', function (): void {
    $teams = MediaFolder::factory()->create(['name' => 'Teams']);
    MediaFolder::factory()->create(['name' => 'Teamsport']);

    expect(MediaFolder::query()->named('teams')->pluck('id')->all())->toBe([$teams->id])
        ->and(MediaFolder::query()->named('TEAMS')->pluck('id')->all())->toBe([$teams->id])
        ->and(MediaFolder::query()->named('Team')->exists())->toBeFalse();
});
