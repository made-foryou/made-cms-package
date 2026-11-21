<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Content\Actions\CropImage;
use Made\CmsPackage\Content\Actions\RenameMedia;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\CropArea;
use Made\CmsPackage\Content\Data\CropMedia;
use Made\CmsPackage\Content\Data\MediaVersion;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Enums\MediaFileRole;
use Made\CmsPackage\Content\Events\MediaCropped;
use Made\CmsPackage\Content\Exceptions\MediaNotCroppable;
use Made\CmsPackage\Content\Models\Media;

beforeEach(function (): void {
    Storage::fake('public');
});

function cropped(string $path, string $filename = 'teamfoto.jpg'): Media
{
    return UploadMedia::run(new UploadedMedia($path, $filename));
}

/**
 * @return array{0: int, 1: int, mime: string}
 */
function storedSize(?string $path): array
{
    $size = getimagesizefromstring((string) Storage::disk('public')->get((string) $path));

    expect($size)->toBeArray();

    return [(int) $size[0], (int) $size[1], 'mime' => (string) $size['mime']];
}

function cropRefused(Media $media, CropMedia $crop): string
{
    try {
        CropImage::run($media, $crop);
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toBe(['crop']);

        return $exception->errors()['crop'][0];
    }

    test()->fail('The crop was stored.');
}

it('cuts the area from the original and scales it down to the maximum width', function (): void {
    config(['made-cms-package.media.image.max_width' => 400]);
    $media = cropped(quadrants(800, 600));

    $media = CropImage::run($media, new CropMedia(new CropArea(80, 60, 640, 360), 0.25, 0.75));

    expect([$media->width, $media->height])->toBe([400, 225])
        ->and(storedSize($media->path))->toBe([400, 225, 'mime' => 'image/webp'])
        ->and($media->mime)->toBe('image/webp')
        ->and($media->size)->toBe(Storage::disk('public')->size($media->path))
        ->and([$media->original_width, $media->original_height])->toBe([800, 600])
        ->and([$media->focal_x, $media->focal_y])->toBe([0.25, 0.75])
        ->and($media->crop)->toBe(['x' => 80, 'y' => 60, 'width' => 640, 'height' => 360, 'rotation' => 0, 'flip' => false, 'scale_down' => true])
        ->and(quadrantsOf((string) Storage::disk('public')->get($media->path)))->toBe(['red', 'green', 'blue', 'yellow'])
        ->and(Media::query()->findOrFail($media->id)->crop)->toBeStoredAs($media->crop);
});

it('keeps the size of the area when the photo is not scaled down, still as WebP', function (): void {
    config(['made-cms-package.media.image.max_width' => 400]);
    $media = cropped(quadrants(800, 600));

    $media = CropImage::run($media, new CropMedia(new CropArea(80, 60, 640, 360), scaleDown: false));

    expect(storedSize($media->path))->toBe([640, 360, 'mime' => 'image/webp'])
        ->and($media->crop['scale_down'] ?? null)->toBeFalse();
});

it('turns and mirrors the photo as the editor saw it', function (int $rotation, bool $flip, array $size, array $colours): void {
    $media = cropped(quadrants(400, 300));
    [$width, $height] = CropArea::turned(400, 300, $rotation);

    $media = CropImage::run($media, new CropMedia(new CropArea(0, 0, $width, $height, $rotation, $flip)));

    expect(storedSize($media->path))->toBe([...$size, 'mime' => 'image/webp'])
        ->and(quadrantsOf((string) Storage::disk('public')->get($media->path)))->toBe($colours);
})->with([
    'mirrored' => [0, true, [400, 300], ['green', 'red', 'yellow', 'blue']],
    'a quarter right' => [90, false, [300, 400], ['blue', 'red', 'yellow', 'green']],
    'a quarter right, mirrored' => [90, true, [300, 400], ['red', 'blue', 'green', 'yellow']],
    'half' => [180, false, [400, 300], ['yellow', 'blue', 'green', 'red']],
    'a quarter left' => [270, false, [300, 400], ['green', 'yellow', 'red', 'blue']],
]);

it('scales a turned photo down to the maximum width it has once it is turned', function (): void {
    config(['made-cms-package.media.image.max_width' => 200]);
    $media = cropped(quadrants(800, 600));

    $media = CropImage::run($media, new CropMedia(new CropArea(0, 0, 600, 800, 90, true)));

    expect(storedSize($media->path))->toBe([200, 267, 'mime' => 'image/webp'])
        ->and(quadrantsOf((string) Storage::disk('public')->get($media->path)))->toBe(['red', 'blue', 'green', 'yellow']);
});

it('cuts a part of the turned photo', function (): void {
    $media = cropped(quadrants(400, 300));

    $media = CropImage::run($media, new CropMedia(new CropArea(0, 0, 300, 200, 90)));

    $colours = quadrantsOf((string) Storage::disk('public')->get($media->path));

    expect(storedSize($media->path))->toBe([300, 200, 'mime' => 'image/webp'])
        ->and([$colours[0], $colours[1]])->toBe(['blue', 'red']);
});

it('keeps the original as it was uploaded', function (): void {
    $path = quadrants(800, 600);
    $media = cropped($path);

    $media = CropImage::run($media, new CropMedia(new CropArea(0, 0, 400, 300)));

    expect(Storage::disk('public')->get((string) $media->original_path))->toBe(file_get_contents($path));
});

it('cuts from the original again, not from the former crop', function (): void {
    $media = cropped(photo(400, 300));

    $media = CropImage::run($media, new CropMedia(new CropArea(0, 0, 200, 300)));
    expect(array_unique(quadrantsOf((string) Storage::disk('public')->get($media->path))))->toBe(['photo']);

    $media = CropImage::run($media, new CropMedia(new CropArea(200, 0, 200, 300)));
    expect(array_unique(quadrantsOf((string) Storage::disk('public')->get($media->path))))->toBe(['black'])
        ->and(storedSize($media->path))->toBe([200, 300, 'mime' => 'image/webp']);
});

it('crops the file as it is now, under the name it got after the editor opened it', function (): void {
    $disk = Storage::disk('public');
    $opened = cropped(quadrants(800, 600));
    RenameMedia::run(Media::query()->findOrFail($opened->id), 'clubavond');

    $media = CropImage::run($opened, new CropMedia(new CropArea(0, 0, 400, 300)));

    expect($media->name)->toBe('clubavond')
        ->and($media->path)->toEndWith('/clubavond.webp')
        ->and(Media::query()->findOrFail($media->id)->path)->toBe($media->path)
        ->and($disk->allFiles())->toEqualCanonicalizing([$media->path, (string) $media->thumb_path, (string) $media->original_path]);
});

it('keeps the files the row holds when it is cropped and renamed back and forth within one transaction', function (): void {
    $disk = Storage::disk('public');
    $media = cropped(quadrants(800, 600));

    $media = DB::transaction(function () use ($media): Media {
        $media = CropImage::run($media, new CropMedia(new CropArea(0, 0, 400, 300)));
        $media = RenameMedia::run($media, 'clubavond');
        $media = RenameMedia::run($media, 'teamfoto');

        return CropImage::run($media, new CropMedia(new CropArea(0, 0, 400, 300), 0.2, 0.2));
    });

    expect($media->path)->toEndWith('/teamfoto.webp')
        ->and(storedSize($media->path))->toBe([400, 300, 'mime' => 'image/webp'])
        ->and($disk->allFiles())->toEqualCanonicalizing([$media->path, (string) $media->thumb_path, (string) $media->original_path]);
});

it('cuts the photo again when it was cropped after the editor opened it, even if only the focal point moved', function (): void {
    $opened = cropped(quadrants(800, 600));
    CropImage::run(Media::query()->findOrFail($opened->id), new CropMedia(new CropArea(0, 0, 400, 300)));

    $media = CropImage::run($opened, new CropMedia(CropArea::whole(800, 600), 0.3, 0.7));

    expect($media->crop)->toBeNull()
        ->and(storedSize($media->path))->toBe([800, 600, 'mime' => 'image/webp'])
        ->and([$media->focal_x, $media->focal_y])->toBe([0.3, 0.7]);
});

it('refuses an area outside the photo and changes nothing', function (CropArea $area): void {
    $media = cropped(quadrants(400, 300));
    $before = Media::query()->findOrFail($media->id)->getAttributes();
    $files = Storage::disk('public')->allFiles();

    expect(cropRefused($media, new CropMedia($area)))->toBe('De uitsnede valt buiten de foto. Herlaad de pagina en probeer het opnieuw.')
        ->and(Media::query()->findOrFail($media->id)->getAttributes())->toBe($before)
        ->and(Storage::disk('public')->allFiles())->toBe($files);
})->with([
    'too wide' => [new CropArea(1, 0, 400, 300)],
    'too high' => [new CropArea(0, 0, 400, 301)],
    'left of the photo' => [new CropArea(-1, 0, 100, 100)],
    'without size' => [new CropArea(0, 0, 0, 100)],
    'as high as the photo is wide, not turned' => [new CropArea(0, 0, 300, 400)],
    'as wide as the photo, turned' => [new CropArea(0, 0, 400, 300, 90)],
    'turned at an angle' => [new CropArea(0, 0, 100, 100, 45)],
]);

it('reads the area on the photo turned upright by its EXIF orientation', function (): void {
    $media = cropped(quadrants(300, 200, orientation: 6));

    expect([$media->original_width, $media->original_height])->toBe([200, 300]);

    $media = CropImage::run($media, new CropMedia(new CropArea(0, 0, 200, 150)));

    expect(storedSize($media->path))->toBe([200, 150, 'mime' => 'image/webp'])
        ->and(cropRefused($media, new CropMedia(new CropArea(0, 0, 300, 200))))->toBeString();
})->skip(! extension_loaded('exif'), 'Reading the orientation needs ext-exif.');

it('writes the photo at a new address with a new thumbnail, and takes the former files off the disk', function (): void {
    $disk = Storage::disk('public');
    $media = cropped(quadrants(800, 600));
    $home = dirname((string) $media->original_path);
    $uploaded = [$media->path, (string) $media->thumb_path];

    $first = CropImage::run($media, new CropMedia(new CropArea(0, 0, 800, 300)));

    expect($first->path)->toMatch('#^media/[0-9a-z]{26}/[0-9a-z]{8}/teamfoto\.webp$#')
        ->and(dirname($first->path, 2))->toBe($home)
        ->and($first->thumb_path)->toMatch('#^media/[0-9a-z]{26}/thumb-[0-9A-Za-z]{40}\.webp$#')
        ->and(dirname((string) $first->thumb_path))->toBe($home)
        ->and($disk->exists($uploaded[0]))->toBeFalse()
        ->and($disk->exists($uploaded[1]))->toBeFalse()
        ->and(storedSize($first->thumb_path))->toBe([800, 300, 'mime' => 'image/webp']);

    $second = CropImage::run($first, new CropMedia(new CropArea(0, 0, 300, 300)));

    expect($second->path)->not->toBe($first->path)
        ->and($second->thumb_path)->not->toBe($first->thumb_path)
        ->and($disk->directoryExists(dirname($first->path)))->toBeFalse()
        ->and($disk->exists((string) $first->thumb_path))->toBeFalse()
        ->and($disk->allFiles())->toEqualCanonicalizing([$second->path, (string) $second->thumb_path, (string) $second->original_path]);
});

it('gives the cut photo its narrower sizes in its own folder, and takes the former sizes off the disk', function (): void {
    $disk = Storage::disk('public');
    $media = cropped(quadrants(2400, 1600));
    $uploaded = array_column((array) $media->variants, 'path');

    $first = CropImage::run($media, new CropMedia(new CropArea(0, 0, 2400, 800)));
    $folder = dirname($first->path);

    expect($uploaded)->toHaveCount(3)
        ->and($first->variants)->toBe([
            ['path' => "{$folder}/480w.webp", 'width' => 480, 'height' => 160],
            ['path' => "{$folder}/960w.webp", 'width' => 960, 'height' => 320],
            ['path' => "{$folder}/1440w.webp", 'width' => 1440, 'height' => 480],
        ])
        ->and(storedSize("{$folder}/960w.webp"))->toBe([960, 320, 'mime' => 'image/webp']);

    foreach ($uploaded as $file) {
        expect($disk->exists($file))->toBeFalse();
    }

    $second = CropImage::run($first, new CropMedia(new CropArea(0, 0, 1000, 1000)));

    expect(array_column((array) $second->variants, 'width'))->toBe([480, 960])
        ->and($disk->directoryExists($folder))->toBeFalse()
        ->and($disk->allFiles())->toEqualCanonicalizing(MediaVersion::held($second));
});

it('gives a photo that is not scaled down the sizes narrower than itself', function (): void {
    $media = cropped(quadrants(2400, 1600));

    $media = CropImage::run($media, new CropMedia(new CropArea(0, 0, 1200, 800), scaleDown: false));

    expect(array_column((array) $media->variants, 'width'))->toBe([480, 960]);
});

it('writes the new photo and its thumbnail public and keeps the original private, on a disk that is private itself', function (): void {
    Storage::fake('public', ['visibility' => 'private']);
    $disk = Storage::disk('public');
    $media = cropped(quadrants(800, 600));

    $media = CropImage::run($media, new CropMedia(new CropArea(0, 0, 800, 300)));

    expect($disk->getVisibility($media->path))->toBe('public')
        ->and($disk->getVisibility((string) $media->thumb_path))->toBe('public')
        ->and($disk->getVisibility((string) $media->original_path))->toBe('private')
        ->and(permissionsOf($disk->path(dirname($media->path))))->toBe('755');
})->skipOnWindows('A file on Windows cannot be private.');

it('hands the disk the new photo and its thumbnail with a cache header for a year', function (): void {
    $disk = recordMediaDisk(['visibility' => 'private']);
    $media = cropped(quadrants(800, 600));

    $media = CropImage::run($media, new CropMedia(new CropArea(0, 0, 800, 300)));

    $public = ['visibility' => 'public', 'directory_visibility' => 'public', 'CacheControl' => MediaFileRole::CACHE_CONTROL];

    expect($disk->written($media->path))->toBe($public)
        ->and($disk->written((string) $media->thumb_path))->toBe($public)
        ->and($disk->written(dirname($media->path).'/480w.webp'))->toBe($public);
});

it('stores only the focal point when the area stays as it was', function (): void {
    $media = cropped(quadrants(800, 600));
    $files = Storage::disk('public')->allFiles();

    $media = CropImage::run($media, new CropMedia(CropArea::whole(800, 600), 0.3, 0.7));

    expect([$media->focal_x, $media->focal_y])->toBe([0.3, 0.7])
        ->and($media->crop)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe($files);

    $media = CropImage::run($media, new CropMedia(new CropArea(0, 0, 400, 300)));
    $files = Storage::disk('public')->allFiles();
    $media = CropImage::run($media, new CropMedia(new CropArea(0, 0, 400, 300), 0.1, 0.2));

    expect([$media->focal_x, $media->focal_y])->toBe([0.1, 0.2])
        ->and(Storage::disk('public')->allFiles())->toBe($files);
});

it('forgets the crop when the editor goes back to the whole original', function (): void {
    $media = cropped(quadrants(800, 600));

    $media = CropImage::run($media, new CropMedia(new CropArea(0, 0, 400, 300)));
    $media = CropImage::run($media, new CropMedia(CropArea::whole(800, 600)));

    expect($media->crop)->toBeNull()
        ->and(storedSize($media->path))->toBe([800, 600, 'mime' => 'image/webp']);
});

it('keeps the focal point on the photo, to four decimals', function (float $x, float $y, array $stored): void {
    $media = cropped(quadrants(400, 300));

    $media = CropImage::run($media, new CropMedia(CropArea::whole(400, 300), $x, $y));

    expect([$media->focal_x, $media->focal_y])->toBe($stored)
        ->and([Media::query()->findOrFail($media->id)->focal_x, Media::query()->findOrFail($media->id)->focal_y])->toBe($stored);
})->with([
    'outside' => [1.4, -0.2, [1.0, 0.0]],
    'many decimals' => [0.123456, 0.987654, [0.1235, 0.9877]],
]);

it('refuses a file that cannot be cropped', function (Closure $file): void {
    $media = cropped($file(), 'bestand');

    CropImage::run($media, new CropMedia(new CropArea(0, 0, 10, 10)));
})->with([
    'an svg' => [fn (): string => mediaFixture('logo.svg')],
    'a pdf' => [fn (): string => mediaFixture('wedstrijdschema.pdf')],
    'an animated gif' => [fn (): string => animatedGif()],
])->throws(MediaNotCroppable::class);

it('refuses to crop a photo whose original is gone', function (): void {
    $media = cropped(quadrants(400, 300));
    Storage::disk('public')->delete((string) $media->original_path);

    expect(cropRefused($media, new CropMedia(new CropArea(0, 0, 100, 100))))
        ->toBe('Het origineel van deze foto is niet meer te lezen, dus hij kan niet opnieuw bijgesneden worden.');
});

it('refuses to crop a photo whose original cannot be read', function (): void {
    $media = cropped(quadrants(400, 300));
    Storage::disk('public')->put((string) $media->original_path, 'geen foto');

    expect(cropRefused($media, new CropMedia(new CropArea(0, 0, 100, 100))))
        ->toBe('Het origineel van deze foto is niet meer te lezen, dus hij kan niet opnieuw bijgesneden worden.');
});

it('tells that a photo was cropped, after the commit', function (): void {
    Event::fake([MediaCropped::class]);
    $media = cropped(quadrants(400, 300));

    CropImage::run($media, new CropMedia(new CropArea(0, 0, 100, 100)));
    CropImage::run($media->refresh(), new CropMedia(new CropArea(0, 0, 100, 100), 0.2, 0.2));

    Event::assertDispatchedTimes(MediaCropped::class, 2);
    Event::assertDispatched(MediaCropped::class, fn (MediaCropped $event): bool => $event->media->is($media));
});

it('takes its files off the disk again when the row cannot be stored', function (): void {
    $media = cropped(quadrants(400, 300));
    $files = Storage::disk('public')->allFiles();
    Media::updating(static fn (): never => throw new RuntimeException('The row cannot be stored.'));

    expect(fn (): Media => CropImage::run($media, new CropMedia(new CropArea(0, 0, 100, 100))))->toThrow(RuntimeException::class)
        ->and(Storage::disk('public')->allFiles())->toBe($files)
        ->and(Media::query()->findOrFail($media->id)->path)->toBe($media->path);
});

it('keeps the files of a committed crop when something after the commit fails', function (): void {
    $disk = Storage::disk('public');
    $media = cropped(quadrants(800, 600));
    Event::listen(MediaCropped::class, static fn (): never => throw new RuntimeException('A listener failed.'));

    expect(fn (): Media => CropImage::run($media, new CropMedia(new CropArea(0, 0, 400, 300))))->toThrow(RuntimeException::class);

    $stored = Media::query()->findOrFail($media->id);

    expect($stored->path)->not->toBe($media->path)
        ->and($disk->exists($stored->path))->toBeTrue()
        ->and($disk->exists((string) $stored->thumb_path))->toBeTrue()
        ->and(storedSize($stored->path))->toBe([400, 300, 'mime' => 'image/webp']);
});

it('refuses a file that is gone, without writing', function (): void {
    $media = cropped(quadrants(400, 300));
    $files = Storage::disk('public')->allFiles();
    Media::query()->whereKey($media->id)->delete();

    expect(fn (): Media => CropImage::run($media, new CropMedia(new CropArea(0, 0, 100, 100))))->toThrow(ModelNotFoundException::class)
        ->and(Storage::disk('public')->allFiles())->toBe($files);
});
