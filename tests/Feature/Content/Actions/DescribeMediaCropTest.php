<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Content\Actions\CropImage;
use Made\CmsPackage\Content\Actions\DescribeMediaCrop;
use Made\CmsPackage\Content\Actions\FindMedia;
use Made\CmsPackage\Content\Actions\SyncMediaUsage;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\CropArea;
use Made\CmsPackage\Content\Data\CropMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Exceptions\MediaNotCroppable;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    Storage::fake('public');
    useStripTypes();
});

it('describes a photo as it was uploaded: its whole original, scaled down', function (): void {
    CarbonImmutable::setTestNow('2026-10-09 14:00:00');
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    $media = UploadMedia::run(new UploadedMedia(quadrants(1200, 800, 'png'), 'teamfoto.png', $folder->id));

    $crop = DescribeMediaCrop::run($media);

    expect($crop->file)->toEqual(FindMedia::run([$media->id])->find($media->id))
        ->and($crop->folderId)->toBe($folder->id)
        ->and([$crop->originalWidth, $crop->originalHeight])->toBe([1200, 800])
        ->and($crop->originalSize)->toBe(Storage::disk('public')->size((string) $media->original_path))
        ->and($crop->originalExtension)->toBe('png')
        ->and($crop->area)->toEqual(CropArea::whole(1200, 800))
        ->and($crop->scaleDown)->toBeTrue()
        ->and($crop->pages)->toBe(0)
        ->and($crop->uploadedAt?->toDateTimeString())->toBe('2026-10-09 14:00:00');
});

it('describes a cropped photo by its crop', function (): void {
    $media = UploadMedia::run(new UploadedMedia(quadrants(1200, 800), 'teamfoto.jpg'));
    $media = CropImage::run($media, new CropMedia(new CropArea(10, 20, 600, 400, 90, true), scaleDown: false));

    $crop = DescribeMediaCrop::run($media);

    expect($crop->area)->toEqual(new CropArea(10, 20, 600, 400, 90, true))
        ->and($crop->scaleDown)->toBeFalse()
        ->and($crop->folderId)->toBeNull();
});

it('starts from the whole original when the stored crop no longer fits it', function (): void {
    $media = Media::factory()->create(['crop' => ['x' => 0, 'y' => 0, 'width' => 5000, 'height' => 10, 'rotation' => 0, 'flip' => false, 'scale_down' => false]]);

    $crop = DescribeMediaCrop::run($media);

    expect($crop->area)->toEqual(CropArea::whole(1920, 1280))
        ->and($crop->scaleDown)->toBeFalse()
        ->and($crop->originalSize)->toBeNull();
});

it('counts the pages the photo is on, without the deleted ones', function (): void {
    $media = Media::factory()->create();
    $pages = Page::factory()->count(3)->withStrips([storedStrip('hero', ['foto' => $media->id])])->create();
    $pages->each(fn (Page $page) => SyncMediaUsage::run($page));
    $pages[2]->delete();

    expect(DescribeMediaCrop::run($media)->pages)->toBe(2);
});

it('describes no file that cannot be cropped', function (Closure $media): void {
    DescribeMediaCrop::run($media());
})->with([
    'an svg' => [fn (): Media => Media::factory()->create(['path' => 'media/01k9/logo.svg', 'original_path' => null, 'thumb_path' => null, 'mime' => 'image/svg+xml', 'original_width' => null, 'original_height' => null])],
    'a document' => [fn (): Media => Media::factory()->document()->create()],
    'a photo without the size of its original' => [fn (): Media => Media::factory()->create(['original_width' => null, 'original_height' => null])],
])->throws(MediaNotCroppable::class);
