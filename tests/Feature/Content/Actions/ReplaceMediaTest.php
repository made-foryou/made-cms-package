<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\ReplaceMedia;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\MediaVersion;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Enums\MediaFileRole;
use Made\CmsPackage\Content\Events\MediaReplaced;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;

beforeEach(function (): void {
    Storage::fake('public');
});

function libraryFile(string $path, ?int $folderId = null): Media
{
    return UploadMedia::run(new UploadedMedia($path, basename($path), $folderId));
}

function replaceWith(Media $media, string $path, ?int $by = null): Media
{
    return ReplaceMedia::run($media, new UploadedMedia($path, basename($path), null, $by));
}

it('keeps the id and puts the new photo in a folder of its own, with the former version waiting', function (): void {
    $editor = Editor::factory()->create();
    $folder = MediaFolder::factory()->create();
    $media = libraryFile(photo(2400, 1600, 'jpg', 'teamfoto.jpg'), $folder->id);
    $media->update(['alt' => 'Het eerste team', 'focal_x' => 0.25, 'focal_y' => 0.75]);
    $former = MediaVersion::current($media->refresh());

    $replaced = replaceWith($media, photo(1600, 1200, 'png', 'Teamfoto 2026.png'), $editor->id);

    $stored = Media::query()->findOrFail($media->id);
    $previous = MediaVersion::waiting($stored);

    expect($replaced->id)->toBe($media->id)
        ->and($stored->version)->toBe(2)
        ->and($stored->name)->toBe('teamfoto-2026')
        ->and($stored->path)->toMatch("#^media/{$stored->ulid}/[0-9a-z]{8}/teamfoto-2026\\.webp\$#")
        ->and(dirname((string) $stored->original_path))->toBe(dirname($stored->path))
        ->and(dirname((string) $stored->thumb_path))->toBe(dirname($stored->path))
        ->and([$stored->width, $stored->height, $stored->original_width, $stored->original_height])->toBe([1600, 1200, 1600, 1200])
        ->and($stored->alt)->toBe('Het eerste team')
        ->and($stored->folder_id)->toBe($folder->id)
        ->and([$stored->focal_x, $stored->focal_y, $stored->crop])->toBe([null, null, null])
        ->and($stored->replaced_by)->toBe($editor->id)
        ->and($stored->replaced_at)->not->toBeNull()
        ->and($stored->variants)->toBe([
            ['path' => dirname($stored->path).'/480w.webp', 'width' => 480, 'height' => 360],
            ['path' => dirname($stored->path).'/960w.webp', 'width' => 960, 'height' => 720],
            ['path' => dirname($stored->path).'/1440w.webp', 'width' => 1440, 'height' => 1080],
        ])
        ->and($previous?->toArray())->toBe($former->toArray())
        ->and($previous?->variantPaths())->toBe(["media/{$stored->ulid}/480w.webp", "media/{$stored->ulid}/960w.webp", "media/{$stored->ulid}/1440w.webp"])
        ->and(Storage::disk('public')->allFiles())->toEqualCanonicalizing(MediaVersion::held($stored));
});

it('replaces a document with a document as it came in, in a folder of its own', function (): void {
    $media = libraryFile(mediaFixture('wedstrijdschema.pdf'));

    replaceWith($media, mediaFixture('wedstrijdschema.pdf', 'Schema voorjaar.pdf'));

    $stored = Media::query()->findOrFail($media->id);

    expect($stored->path)->toMatch("#^media/{$stored->ulid}/[0-9a-z]{8}/schema-voorjaar\\.pdf\$#")
        ->and($stored->original_path)->toBeNull()
        ->and($stored->mime)->toBe('application/pdf')
        ->and(MediaVersion::waiting($stored)?->path)->toBe($media->path);
});

it('refuses a file of another kind and stores nothing', function (Closure $file, Closure $replacement, string $message): void {
    $media = libraryFile($file());
    $before = Storage::disk('public')->allFiles();

    try {
        replaceWith($media, $replacement());
        test()->fail('The file was replaced.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['file'][0] ?? null)->toBe($message);
    }

    expect(Storage::disk('public')->allFiles())->toEqualCanonicalizing($before)
        ->and(Media::query()->findOrFail($media->id)->version)->toBe(1);
})->with([
    'an image by a pdf' => [fn (): string => photo(800, 600), fn (): string => mediaFixture('wedstrijdschema.pdf'), 'Kies een afbeelding: je vervangt een afbeelding.'],
    'a pdf by an image' => [fn (): string => mediaFixture('wedstrijdschema.pdf'), fn (): string => photo(800, 600), 'Kies een document: je vervangt een document.'],
    'a video by an image' => [fn (): string => mediaFixture('samenvatting.mp4'), fn (): string => mediaFixture('logo.svg'), 'Kies een video: je vervangt een video.'],
]);

it('takes an svg or an animated gif for a photo, as they came in', function (): void {
    $media = libraryFile(photo(800, 600));

    replaceWith($media, mediaFixture('logo.svg'));

    $stored = Media::query()->findOrFail($media->id);

    expect($stored->mime)->toBe('image/svg+xml')
        ->and($stored->original_path)->toBeNull()
        ->and($stored->thumb_path)->toBeNull()
        ->and($stored->variants)->toBeNull()
        ->and(MediaVersion::waiting($stored)?->variantPaths())->toBe(["media/{$stored->ulid}/480w.webp"]);
});

it('numbers each version the highest so far plus one and pushes the oldest off the disk', function (): void {
    $media = libraryFile(photo(800, 600, 'jpg', 'een.jpg'));
    $first = MediaVersion::current($media->refresh());

    replaceWith($media, photo(800, 600, 'jpg', 'twee.jpg'));
    $second = MediaVersion::current(Media::query()->findOrFail($media->id));
    replaceWith($media, photo(800, 600, 'jpg', 'drie.jpg'));

    $stored = Media::query()->findOrFail($media->id);

    expect($stored->version)->toBe(3)
        ->and(MediaVersion::waiting($stored)?->version)->toBe(2)
        ->and(MediaVersion::waiting($stored)?->path)->toBe($second->path)
        ->and(Storage::disk('public')->allFiles())->toEqualCanonicalizing(MediaVersion::held($stored));

    foreach ($first->files() as $file) {
        expect(Storage::disk('public')->exists($file))->toBeFalse();
    }
});

it('writes the new files by their roles and makes the version that waits private', function (): void {
    $disk = recordMediaDisk(['visibility' => 'private']);
    $media = libraryFile(photo(800, 600));
    $former = MediaVersion::current($media->refresh());

    replaceWith($media, photo(800, 600, 'jpg', 'nieuw.jpg'));

    $stored = Media::query()->findOrFail($media->id);
    $public = ['visibility' => 'public', 'directory_visibility' => 'public', 'CacheControl' => MediaFileRole::CACHE_CONTROL];

    expect($disk->written($stored->path))->toBe($public)
        ->and($disk->written((string) $stored->thumb_path))->toBe($public)
        ->and($disk->written(dirname($stored->path).'/480w.webp'))->toBe($public)
        ->and($disk->written((string) $stored->original_path)['visibility'] ?? null)->toBe('private')
        ->and($disk->written($former->path)['visibility'] ?? null)->toBe('private')
        ->and($disk->written((string) $former->thumbPath)['visibility'] ?? null)->toBe('private')
        ->and($disk->written($former->variantPaths()[0])['visibility'] ?? null)->toBe('private');
});

it('makes the version that waits private on a local disk', function (): void {
    $media = libraryFile(mediaFixture('wedstrijdschema.pdf'));

    replaceWith($media, mediaFixture('wedstrijdschema.pdf', 'nieuw.pdf'));

    expect(Storage::disk('public')->getVisibility($media->path))->toBe('private')
        ->and(Storage::disk('public')->getVisibility(Media::query()->findOrFail($media->id)->path))->toBe('public');
})->skipOnWindows();

it('tells the replacement once the change is committed', function (): void {
    Event::fake([MediaReplaced::class]);
    $media = libraryFile(photo(800, 600));

    replaceWith($media, photo(800, 600, 'jpg', 'nieuw.jpg'));

    Event::assertDispatched(MediaReplaced::class, fn (MediaReplaced $event): bool => $event->media->id === $media->id && $event->media->version === 2);
});

it('takes the new folder off the disk again when the row is gone', function (): void {
    $media = libraryFile(photo(800, 600));
    $files = Storage::disk('public')->allFiles();
    Media::query()->whereKey($media->id)->delete();

    expect(fn (): Media => replaceWith($media, photo(800, 600, 'jpg', 'nieuw.jpg')))->toThrow(ModelNotFoundException::class)
        ->and(Storage::disk('public')->allFiles())->toEqualCanonicalizing($files);
});

it('keeps the replacement when the version that waits cannot be made private', function (): void {
    Exceptions::fake();
    $media = libraryFile(photo(800, 600));
    Storage::disk('public')->delete($media->path);

    replaceWith($media, photo(800, 600, 'jpg', 'nieuw.jpg'));

    expect(Media::query()->findOrFail($media->id)->version)->toBe(2);
    Exceptions::assertReportedCount(1);
});
