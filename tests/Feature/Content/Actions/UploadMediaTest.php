<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Events\MediaUploaded;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Exceptions\MediaFolderNotFound;
use Made\CmsPackage\Content\Exceptions\MediaNotStored;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;

beforeEach(function (): void {
    Storage::fake('public');
});

function upload(string $path, ?string $filename = null, ?int $folderId = null, ?int $uploadedBy = null): Media
{
    return UploadMedia::run(new UploadedMedia($path, $filename ?? basename($path), $folderId, $uploadedBy));
}

function uploadRefused(string $path, ?string $filename = null): string
{
    try {
        upload($path, $filename);
    } catch (ValidationException $exception) {
        return $exception->errors()['file'][0] ?? '';
    }

    test()->fail('The file was stored.');
}

/**
 * Every file on the media disk.
 *
 * @return list<string>
 */
function mediaOnDisk(): array
{
    return Storage::disk('public')->allFiles();
}

it('scales a jpg of 4000 px down to a WebP of 1920 px with the original and a thumbnail next to it', function (): void {
    $path = photo(4000, 2667, 'jpg', 'teamfoto-merwestad-4.jpg');

    $media = upload($path);

    $disk = Storage::disk('public');
    $stored = Media::query()->findOrFail($media->id);

    expect($stored->kind)->toBe(MediaKind::Image)
        ->and($stored->name)->toBe('teamfoto-merwestad-4')
        ->and($stored->path)->toMatch('#^media/[0-9a-z]{26}/teamfoto-merwestad-4\.webp$#')
        ->and($stored->original_path)->toMatch('#^media/[0-9a-z]{26}/original-[0-9A-Za-z]{40}\.jpg$#')
        ->and(dirname((string) $stored->original_path))->toBe(dirname($stored->path))
        ->and($stored->mime)->toBe('image/webp')
        ->and([$stored->width, $stored->height])->toBe([1920, 1280])
        ->and($stored->size)->toBe($disk->size($stored->path))
        ->and($stored->alt)->toBeNull()
        ->and($stored->folder_id)->toBeNull()
        ->and(getimagesizefromstring((string) $disk->get($stored->path)))->toMatchArray([0 => 1920, 1 => 1280, 'mime' => 'image/webp'])
        ->and($disk->get((string) $stored->original_path))->toBe(file_get_contents($path))
        ->and($stored->thumb_path)->toMatch('#^media/[0-9a-z]{26}/thumb-[0-9A-Za-z]{40}\.webp$#')
        ->and(dirname((string) $stored->thumb_path))->toBe(dirname($stored->path))
        ->and(getimagesizefromstring((string) $disk->get((string) $stored->thumb_path)))->toMatchArray([0 => 540, 1 => 360, 'mime' => 'image/webp'])
        ->and(mediaOnDisk())->toHaveCount(3);
});

it('keeps the upright size of the original of a photo', function (): void {
    $media = upload(photo(4000, 2667));

    expect([$media->original_width, $media->original_height])->toBe([4000, 2667])
        ->and([$media->fresh()?->original_width, $media->fresh()?->original_height])->toBe([4000, 2667]);
});

it('keeps the upright size of the original of a turned photo', function (): void {
    $media = upload(photo(3000, 2000, orientation: 6));

    expect([$media->original_width, $media->original_height])->toBe([2000, 3000]);
})->skip(! extension_loaded('exif'), 'Reading the orientation needs ext-exif.');

it('has no original size for a file kept as it came in', function (Closure $file): void {
    $media = upload($file());

    expect([$media->original_width, $media->original_height])->toBe([null, null]);
})->with([
    'an svg' => [fn (): string => mediaFixture('logo.svg')],
    'a pdf' => [fn (): string => mediaFixture('wedstrijdschema.pdf')],
    'an animated gif' => [fn (): string => animatedGif()],
]);

it('writes every photo as WebP, keeps its original and gives it a thumbnail', function (string $format): void {
    $media = upload(photo(800, 600, $format));

    expect($media->mime)->toBe('image/webp')
        ->and($media->path)->toEndWith('/foto.webp')
        ->and($media->original_path)->toEndWith(".{$format}")
        ->and([$media->width, $media->height])->toBe([800, 600])
        ->and($media->thumb_path)->toEndWith('.webp')
        ->and(getimagesizefromstring((string) Storage::disk('public')->get((string) $media->thumb_path)))->toMatchArray([0 => 480, 1 => 360, 'mime' => 'image/webp']);
})->with(['jpg', 'png', 'webp', 'gif']);

it('gives a small photo a thumbnail of its own size', function (): void {
    $media = upload(photo(400, 300));

    expect(getimagesizefromstring((string) Storage::disk('public')->get((string) $media->thumb_path)))->toMatchArray([0 => 400, 1 => 300, 'mime' => 'image/webp']);
});

it('makes the thumbnail from the photo the site shows, upright', function (): void {
    $media = upload(photo(3000, 2000, orientation: 6));

    expect([$media->width, $media->height])->toBe([1920, 2880])
        ->and(getimagesizefromstring((string) Storage::disk('public')->get((string) $media->thumb_path)))->toMatchArray([0 => 480, 1 => 720]);
})->skip(! extension_loaded('exif'), 'Reading the orientation needs ext-exif.');

it('keeps a file that is no photo exactly as it came in', function (Closure $file, string $name, MediaKind $kind, string $mime, ?array $size): void {
    $path = $file();

    $media = upload($path);

    expect($media->kind)->toBe($kind)
        ->and($media->mime)->toBe($mime)
        ->and($media->path)->toMatch("#^media/[0-9a-z]{26}/{$name}\$#")
        ->and($media->original_path)->toBeNull()
        ->and($media->thumb_path)->toBeNull()
        ->and($media->size)->toBe(filesize($path))
        ->and($media->width === null ? null : [$media->width, $media->height])->toBe($size)
        ->and(Storage::disk('public')->get($media->path))->toBe(file_get_contents($path))
        ->and(mediaOnDisk())->toHaveCount(1);
})->with([
    'an svg' => [fn (): string => mediaFixture('logo.svg'), 'logo.svg', MediaKind::Image, 'image/svg+xml', null],
    'a pdf' => [fn (): string => mediaFixture('wedstrijdschema.pdf'), 'wedstrijdschema.pdf', MediaKind::Document, 'application/pdf', null],
    'an mp4' => [fn (): string => mediaFixture('samenvatting.mp4'), 'samenvatting.mp4', MediaKind::Video, 'video/mp4', null],
    'an animated gif' => [fn (): string => animatedGif(), 'laden.gif', MediaKind::Image, 'image/gif', [40, 30]],
]);

it('names the file after the upload, as a slug without extension', function (string $filename, string $name): void {
    expect(upload(mediaFixture('wedstrijdschema.pdf'), $filename)->name)->toBe($name);
})->with([
    'capitals, spaces and underscores' => ['Wedstrijd Schema_Najaar.PDF', 'wedstrijd-schema-najaar'],
    'dots' => ['schema.najaar.2026.pdf', 'schema-najaar-2026'],
    'accents' => ['Ëindrapport café.pdf', 'eindrapport-cafe'],
    'a path' => ['../../geheim/schema.pdf', 'schema'],
    'no letters or digits' => ['???.pdf', 'document'],
    'a very long name' => [str_repeat('lang ', 60).'.pdf', rtrim(substr(str_repeat('lang-', 60), 0, 100), '-')],
]);

it('takes the extension from the contents, not from the name', function (): void {
    $media = upload(photo(40, 30, 'png', 'foto.jpg'));

    expect($media->name)->toBe('foto')
        ->and($media->original_path)->toEndWith('.png');
});

it('gives two uploads with the same name a place of their own', function (): void {
    $first = upload(mediaFixture('wedstrijdschema.pdf'));
    $second = upload(mediaFixture('wedstrijdschema.pdf'));

    expect($first->name)->toBe($second->name)
        ->and($first->path)->not->toBe($second->path)
        ->and(mediaOnDisk())->toHaveCount(2);
});

it('puts the file in a folder and remembers who uploaded it', function (): void {
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    $editor = Editor::factory()->create();

    $media = upload(mediaFixture('wedstrijdschema.pdf'), folderId: $folder->id, uploadedBy: $editor->id);

    expect($media->refresh()->folder_id)->toBe($folder->id)
        ->and($media->uploaded_by)->toBe($editor->id);
});

it('dispatches an event with the new file', function (): void {
    Event::fake([MediaUploaded::class]);

    $media = upload(mediaFixture('wedstrijdschema.pdf'));

    Event::assertDispatched(MediaUploaded::class, fn (MediaUploaded $event): bool => $event->media->is($media));
});

it('refuses a file that is too large, of an unknown type or unreadable, and stores nothing', function (Closure $file, string $error): void {
    Event::fake([MediaUploaded::class]);
    config(['made-cms-package.media.max_upload_kb' => 4]);

    expect(uploadRefused($file()))->toBe(__("made-cms-package::media.errors.{$error}", ['max' => '4 kB', 'types' => 'jpg, png, webp, gif, svg, pdf of mp4']));

    expect(Media::query()->count())->toBe(0)
        ->and(mediaOnDisk())->toBe([]);
    Event::assertNotDispatched(MediaUploaded::class);
})->with([
    'too large' => [fn (): string => uploadable('groot.pdf', '%PDF-1.4'.str_repeat(' ', 5000)), 'too_large'],
    'an unknown type' => [fn (): string => uploadable('logo-nieuw.psd', "8BPS\x00\x01".str_repeat("\x00", 40)), 'type_unknown'],
    'a broken photo' => [fn (): string => uploadable('kapot.jpg', "\xFF\xD8\xFF\xE0".str_repeat('kapot', 200)), 'unreadable'],
    'an svg with a script' => [fn (): string => uploadable('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), 'svg_unsafe'],
]);

it('refuses a folder that does not exist, and stores nothing', function (): void {
    expect(fn (): Media => upload(mediaFixture('wedstrijdschema.pdf'), folderId: 999))
        ->toThrow(MediaFolderNotFound::class, 'There is no media folder [999].');

    expect(mediaOnDisk())->toBe([]);
});

it('refuses a folder deleted while the file was on its way, and stores nothing', function (): void {
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    MediaFolder::retrieved(static function (MediaFolder $retrieved): void {
        DB::table('cms_media_folders')->where('id', $retrieved->id)->delete();
    });

    expect(fn (): Media => upload(mediaFixture('wedstrijdschema.pdf'), folderId: $folder->id))
        ->toThrow(MediaFolderNotFound::class);

    expect(mediaOnDisk())->toBe([])
        ->and(Media::query()->count())->toBe(0);
});

it('reads the folder again inside the transaction that stores the file', function (): void {
    $folder = MediaFolder::factory()->create();
    $outside = DB::transactionLevel();
    $levels = [];

    DB::listen(static function (QueryExecuted $query) use (&$levels): void {
        if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'cms_media_folders')) {
            $levels[] = DB::transactionLevel();
        }
    });

    upload(mediaFixture('wedstrijdschema.pdf'), folderId: $folder->id);

    expect($levels)->toBe([$outside, $outside + 1]);
});

it('stores nothing on a disk a visitor cannot open', function (): void {
    config([
        'filesystems.disks.local' => ['driver' => 'local', 'root' => storage_path('app/private')],
        'made-cms-package.media.disk' => 'local',
    ]);
    Storage::fake('local');

    expect(fn (): Media => upload(mediaFixture('wedstrijdschema.pdf')))->toThrow(MediaDiskNotUsable::class);

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('takes its files off the disk again when the row cannot be stored', function (): void {
    expect(fn (): Media => upload(photo(400, 300), uploadedBy: 999))->toThrow(QueryException::class);

    expect(Media::query()->count())->toBe(0)
        ->and(mediaOnDisk())->toBe([]);
});

it('says so when the disk does not take the file', function (): void {
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('putFileAs')->andReturnFalse();
    $disk->shouldReceive('deleteDirectory')->once();
    Storage::set('public', $disk);

    expect(fn (): Media => upload(mediaFixture('wedstrijdschema.pdf')))->toThrow(MediaNotStored::class);

    expect(Media::query()->count())->toBe(0);
});
