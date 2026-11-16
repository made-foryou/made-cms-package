<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Content\Actions\RenameMediaFolder;
use Made\CmsPackage\Content\Events\MediaFolderRenamed;
use Made\CmsPackage\Content\Exceptions\MediaFolderNotFound;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;

it('renames a folder, which keeps its files', function (): void {
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    $media = Media::factory()->in($folder)->create();

    $renamed = RenameMediaFolder::run($folder, '  Teams   2026 ');

    expect($renamed->name)->toBe('Teams 2026')
        ->and(MediaFolder::query()->findOrFail($folder->id)->name)->toBe('Teams 2026')
        ->and(Media::query()->findOrFail($media->id)->folder_id)->toBe($folder->id);
});

it('tells that a folder was renamed, and only when its name changed', function (): void {
    Event::fake([MediaFolderRenamed::class]);
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);

    RenameMediaFolder::run($folder, ' Teams ');
    Event::assertNotDispatched(MediaFolderRenamed::class);

    $renamed = RenameMediaFolder::run($folder, 'teams');
    expect($renamed->name)->toBe('teams');
    Event::assertDispatched(MediaFolderRenamed::class, fn (MediaFolderRenamed $event): bool => $event->folder->is($folder) && $event->folder->name === 'teams');
});

it('refuses a name another folder has and keeps its own', function (): void {
    MediaFolder::factory()->create(['name' => 'Nieuws']);
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);

    try {
        RenameMediaFolder::run($folder, 'NIEUWS');
        test()->fail('The folder was renamed.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['name' => ['Er is al een map Nieuws.']]);
    }

    expect(MediaFolder::query()->findOrFail($folder->id)->name)->toBe('Teams');
});

it('refuses a name another folder got at the same moment, as a taken name', function (): void {
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    MediaFolder::updating(static function (): void {
        DB::table('cms_media_folders')->insert(['name' => 'Nieuws', 'created_at' => now(), 'updated_at' => now()]);
    });

    try {
        RenameMediaFolder::run($folder, 'Nieuws');
        test()->fail('The folder was renamed.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['name' => ['Er is al een map Nieuws.']]);
    }

    expect(MediaFolder::query()->findOrFail($folder->id)->name)->toBe('Teams');
});

it('renames the folder as it is now, read inside its transaction', function (): void {
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    $opened = MediaFolder::query()->findOrFail($folder->id);
    RenameMediaFolder::run($folder, 'Nieuws');
    $outside = DB::transactionLevel();
    $levels = [];

    DB::listen(static function (QueryExecuted $query) use (&$levels): void {
        if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'cms_media_folders') && ! str_contains($query->sql, 'lower(name)')) {
            $levels[] = DB::transactionLevel();
        }
    });

    $renamed = RenameMediaFolder::run($opened, 'Nieuws');

    expect($levels)->toBe([$outside + 1])
        ->and($renamed->name)->toBe('Nieuws');
});

it('refuses a folder that is gone', function (): void {
    $folder = MediaFolder::factory()->create();
    MediaFolder::query()->whereKey($folder->id)->delete();

    expect(fn (): MediaFolder => RenameMediaFolder::run($folder, 'Nieuws'))->toThrow(MediaFolderNotFound::class);
});
