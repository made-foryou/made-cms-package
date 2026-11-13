<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Content\Actions\CropImage;
use Made\CmsPackage\Content\Actions\MakeOriginalPreview;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\CropArea;
use Made\CmsPackage\Content\Data\CropMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Exceptions\MediaNotCroppable;
use Made\CmsPackage\Content\Models\Media;

beforeEach(function (): void {
    Storage::fake('public');
});

it('shows the whole original, also of a cropped photo, scaled down as WebP', function (): void {
    config(['made-cms-package.media.image.max_width' => 600]);
    $media = UploadMedia::run(new UploadedMedia(quadrants(1200, 800), 'teamfoto.jpg'));
    $media = CropImage::run($media, new CropMedia(new CropArea(0, 0, 300, 300)));

    $preview = MakeOriginalPreview::run($media);

    expect([$preview->width, $preview->height])->toBe([600, 400])
        ->and([$preview->sourceWidth, $preview->sourceHeight])->toBe([1200, 800])
        ->and($preview->mime)->toBe('image/webp')
        ->and(getimagesizefromstring((string) $preview->contents))->toMatchArray([0 => 600, 1 => 400, 'mime' => 'image/webp'])
        ->and(quadrantsOf((string) $preview->contents))->toBe(['red', 'green', 'blue', 'yellow']);
});

it('shows the original upright, without the metadata of the camera', function (): void {
    $media = UploadMedia::run(new UploadedMedia(quadrants(300, 200, orientation: 6), 'teamfoto.jpg'));

    $preview = (string) MakeOriginalPreview::run($media)->contents;

    expect(getimagesizefromstring($preview))->toMatchArray([0 => 200, 1 => 300])
        ->and($preview)->not->toContain('Exif');
})->skip(! extension_loaded('exif'), 'Reading the orientation needs ext-exif.');

it('refuses an original that is gone', function (): void {
    $media = UploadMedia::run(new UploadedMedia(quadrants(300, 200), 'teamfoto.jpg'));
    Storage::disk('public')->delete((string) $media->original_path);

    MakeOriginalPreview::run($media);
})->throws(ValidationException::class);

it('shows no original of a file without one', function (): void {
    MakeOriginalPreview::run(Media::factory()->document()->create());
})->throws(MediaNotCroppable::class);
