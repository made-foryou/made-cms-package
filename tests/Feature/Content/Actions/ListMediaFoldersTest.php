<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\ListMediaFolders;
use Made\CmsPackage\Content\Data\MediaFolderCount;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;

it('lists every folder from a to z with the number of its files, and all files of the library', function (): void {
    $teams = MediaFolder::factory()->create(['name' => 'Teams']);
    $nieuws = MediaFolder::factory()->create(['name' => 'Nieuws']);
    $leeg = MediaFolder::factory()->create(['name' => 'Locatie']);
    Media::factory()->in($teams)->count(2)->create();
    Media::factory()->in($nieuws)->document()->create();
    Media::factory()->create();

    $folders = ListMediaFolders::run();

    expect($folders->folders)->toEqual([
        new MediaFolderCount($leeg->id, 'Locatie', 0),
        new MediaFolderCount($nieuws->id, 'Nieuws', 1),
        new MediaFolderCount($teams->id, 'Teams', 2),
    ])->and($folders->all)->toBe(4);
});

it('counts only the files of a kind, in the folders and in all files', function (): void {
    $teams = MediaFolder::factory()->create(['name' => 'Teams']);
    Media::factory()->in($teams)->create();
    Media::factory()->in($teams)->document()->create();
    Media::factory()->video()->create();
    Media::factory()->create();

    $folders = ListMediaFolders::run(MediaKind::Image);

    expect($folders->folders)->toEqual([new MediaFolderCount($teams->id, 'Teams', 1)])
        ->and($folders->all)->toBe(2);
});

it('lists no folders in an empty library', function (): void {
    $folders = ListMediaFolders::run();

    expect($folders->folders)->toBe([])->and($folders->all)->toBe(0);
});

it('gives the folders as the CMS shows them', function (): void {
    $teams = MediaFolder::factory()->create(['name' => 'Teams']);
    Media::factory()->in($teams)->create();

    expect(ListMediaFolders::run()->toArray())->toBe([
        'all' => 1,
        'folders' => [['id' => $teams->id, 'name' => 'Teams', 'count' => 1]],
    ]);
});
