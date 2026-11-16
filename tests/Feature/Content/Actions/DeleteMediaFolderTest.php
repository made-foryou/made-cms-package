<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Content\Actions\DeleteMediaFolder;
use Made\CmsPackage\Content\Events\MediaFolderDeleted;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;

it('deletes an empty folder', function (): void {
    Event::fake([MediaFolderDeleted::class]);
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    $other = MediaFolder::factory()->create();
    Media::factory()->in($other)->create();

    DeleteMediaFolder::run($folder);

    expect(MediaFolder::query()->pluck('id')->all())->toBe([$other->id]);
    Event::assertDispatched(MediaFolderDeleted::class, fn (MediaFolderDeleted $event): bool => $event->folder->id === $folder->id && $event->folder->name === 'Teams');
});

it('refuses a folder that holds files, with their number, and keeps it', function (int $files, string $message): void {
    Event::fake([MediaFolderDeleted::class]);
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    Media::factory()->count($files)->in($folder)->create();

    try {
        DeleteMediaFolder::run($folder);
        test()->fail('The folder was deleted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['folder' => [$message]]);
    }

    expect(MediaFolder::query()->whereKey($folder->id)->exists())->toBeTrue();
    Event::assertNotDispatched(MediaFolderDeleted::class);
})->with([
    'one file' => [1, 'Teams heeft nog 1 bestand. Zet het eerst in een andere map.'],
    'more files' => [14, 'Teams heeft nog 14 bestanden. Zet ze eerst in een andere map.'],
]);

it('refuses a folder a file went into after it was opened', function (): void {
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    $opened = MediaFolder::query()->findOrFail($folder->id);
    Media::factory()->in($folder)->create();

    expect(fn () => DeleteMediaFolder::run($opened))->toThrow(ValidationException::class);
});

it('counts the files of the folder inside its transaction, with the folder locked', function (): void {
    $folder = MediaFolder::factory()->create();
    $outside = DB::transactionLevel();
    $levels = [];

    DB::listen(static function (QueryExecuted $query) use (&$levels): void {
        if (str_starts_with($query->sql, 'select')) {
            $levels[] = DB::transactionLevel();
        }
    });

    DeleteMediaFolder::run($folder);

    expect($levels)->not->toBeEmpty()
        ->and(array_unique($levels))->toBe([$outside + 1]);
});

it('does nothing for a folder that is gone already', function (): void {
    Event::fake([MediaFolderDeleted::class]);
    $folder = MediaFolder::factory()->create();
    MediaFolder::query()->whereKey($folder->id)->delete();

    DeleteMediaFolder::run($folder);

    Event::assertNotDispatched(MediaFolderDeleted::class);
});
