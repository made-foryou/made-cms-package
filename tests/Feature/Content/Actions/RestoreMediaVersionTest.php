<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\CropImage;
use Made\CmsPackage\Content\Actions\ReplaceMedia;
use Made\CmsPackage\Content\Actions\RestoreMediaVersion;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\CropArea;
use Made\CmsPackage\Content\Data\CropMedia;
use Made\CmsPackage\Content\Data\MediaVersion;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Events\MediaVersionRestored;
use Made\CmsPackage\Content\Exceptions\MediaNotStored;
use Made\CmsPackage\Content\Models\Media;

beforeEach(function (): void {
    Storage::fake('public');
});

/**
 * A photo of the library replaced once by an editor: version 2 shown, version 1 waiting.
 */
function photoWithTwoVersions(?int $by = null): Media
{
    $media = UploadMedia::run(new UploadedMedia(photo(1200, 800, 'jpg', 'een.jpg'), 'een.jpg'));

    return ReplaceMedia::run($media, new UploadedMedia(photo(900, 600, 'jpg', 'twee.jpg'), 'twee.jpg', null, $by));
}

it('swaps the version that waits with the version the site shows', function (): void {
    $editor = Editor::factory()->create();
    $media = photoWithTwoVersions($editor->id);
    $first = MediaVersion::waiting($media);
    $second = MediaVersion::current($media);

    RestoreMediaVersion::run($media);

    $stored = Media::query()->findOrFail($media->id);

    expect($stored->version)->toBe(1)
        ->and($stored->name)->toBe('een')
        ->and(MediaVersion::current($stored)->toArray())->toBe($first?->toArray())
        ->and(MediaVersion::waiting($stored)?->toArray())->toBe($second->toArray())
        ->and(MediaVersion::waiting($stored)?->placedBy)->toBe($editor->id)
        ->and($stored->replaced_by)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toEqualCanonicalizing(MediaVersion::held($stored));
});

it('swaps back to where it started', function (): void {
    $media = photoWithTwoVersions();
    $start = [MediaVersion::current($media)->toArray(), MediaVersion::waiting($media)?->toArray()];

    RestoreMediaVersion::run($media);
    RestoreMediaVersion::run($media);

    $stored = Media::query()->findOrFail($media->id);

    expect([MediaVersion::current($stored)->toArray(), MediaVersion::waiting($stored)?->toArray()])->toBe($start);
});

it('makes the version it puts back public before the row points at it, and the other private after', function (): void {
    $disk = recordMediaDisk();
    $media = photoWithTwoVersions();
    $first = MediaVersion::waiting($media);
    $second = MediaVersion::current($media);
    $update = 'update '.quoted('cms_media');
    DB::listen(static function (QueryExecuted $query) use ($disk, $update): void {
        if (str_starts_with($query->sql, $update)) {
            $disk->note('update');
        }
    });
    $before = count($disk->calls());

    RestoreMediaVersion::run($media);

    expect($first?->variantPaths())->toHaveCount(2)
        ->and($second->variantPaths())->toHaveCount(1)
        ->and(array_slice($disk->calls(), $before))->toBe([
            "visibility {$first?->path} public",
            "visibility {$first?->thumbPath} public",
            "visibility {$first?->variantPaths()[0]} public",
            "visibility {$first?->variantPaths()[1]} public",
            'update',
            "visibility {$second->path} private",
            "visibility {$second->thumbPath} private",
            "visibility {$second->originalPath} private",
            "visibility {$second->variantPaths()[0]} private",
        ]);
});

it('opens the version it puts back for a visitor on a local disk', function (): void {
    $media = photoWithTwoVersions();

    RestoreMediaVersion::run($media);

    $stored = Media::query()->findOrFail($media->id);

    expect(Storage::disk('public')->getVisibility($stored->path))->toBe('public')
        ->and(Storage::disk('public')->getVisibility((string) $stored->thumb_path))->toBe('public')
        ->and(Storage::disk('public')->getVisibility((string) $stored->original_path))->toBe('private')
        ->and(Storage::disk('public')->getVisibility(MediaVersion::current($stored)->variantPaths()[0]))->toBe('public')
        ->and(Storage::disk('public')->getVisibility((string) MediaVersion::waiting($stored)?->path))->toBe('private')
        ->and(Storage::disk('public')->getVisibility((string) MediaVersion::waiting($stored)?->variantPaths()[0]))->toBe('private');
})->skipOnWindows();

it('refuses a file without a version that waits', function (): void {
    $media = Media::factory()->create();

    try {
        RestoreMediaVersion::run($media);
        test()->fail('A version was put back.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['version'][0] ?? null)->toBe('Er staat geen vorige versie klaar om terug te zetten.');
    }
});

it('keeps the row when the disk does not open the version that waits', function (): void {
    $media = photoWithTwoVersions();
    Storage::disk('public')->delete((string) MediaVersion::waiting($media)?->path);

    expect(fn (): Media => RestoreMediaVersion::run($media))->toThrow(MediaNotStored::class)
        ->and(Media::query()->findOrFail($media->id)->version)->toBe(2);
});

it('tells that a version was put back once the change is committed', function (): void {
    Event::fake([MediaVersionRestored::class]);
    $media = photoWithTwoVersions();

    RestoreMediaVersion::run($media);

    Event::assertDispatched(MediaVersionRestored::class, fn (MediaVersionRestored $event): bool => $event->media->id === $media->id && $event->media->version === 1);
});

it('crops the version it put back from its own original', function (): void {
    $media = photoWithTwoVersions();
    RestoreMediaVersion::run($media);
    $restored = Media::query()->findOrFail($media->id);

    $cropped = CropImage::run($restored, new CropMedia(new CropArea(0, 0, 600, 400)));

    expect(dirname(dirname($cropped->path)))->toBe(dirname((string) $restored->original_path))
        ->and([$cropped->width, $cropped->height])->toBe([600, 400])
        ->and(Storage::disk('public')->allFiles())->toEqualCanonicalizing(MediaVersion::held($cropped->refresh()));
});
