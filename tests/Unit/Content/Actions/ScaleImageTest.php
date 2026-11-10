<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Content\Actions\CheckUploadedMedia;
use Made\CmsPackage\Content\Actions\ScaleImage;
use Made\CmsPackage\Content\Data\ScaledImage;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Exceptions\ImageDriverNotSupported;

function scaled(string $path): ScaledImage
{
    return ScaleImage::run($path, CheckUploadedMedia::run($path));
}

/**
 * @return array{0: int, 1: int, mime: string}
 */
function imageSize(?string $contents): array
{
    $size = getimagesizefromstring((string) $contents);

    expect($size)->toBeArray();

    return [(int) $size[0], (int) $size[1], 'mime' => (string) $size['mime']];
}

it('scales a large photo down to WebP of the maximum width, keeping its proportions', function (): void {
    $image = scaled(photo(4000, 2667));

    expect($image->width)->toBe(1920)
        ->and($image->height)->toBe(1280)
        ->and($image->mime)->toBe('image/webp')
        ->and($image->extension)->toBe('webp')
        ->and(imageSize($image->contents))->toBe([1920, 1280, 'mime' => 'image/webp']);
});

it('never makes a small photo larger, but still writes it as WebP', function (string $format): void {
    $image = scaled(photo(800, 600, $format));

    expect(imageSize($image->contents))->toBe([800, 600, 'mime' => 'image/webp']);
})->with(['jpg', 'png', 'webp', 'gif']);

it('turns a photo upright by its EXIF orientation', function (): void {
    $image = scaled(photo(300, 200, orientation: 6));

    expect([$image->width, $image->height])->toBe([200, 300])
        ->and(imageSize($image->contents))->toBe([200, 300, 'mime' => 'image/webp']);
})->skip(! extension_loaded('exif'), 'Reading the orientation needs ext-exif.');

it('keeps an animation as it is, so it keeps moving', function (): void {
    $image = scaled(animatedGif());

    expect($image->contents)->toBeNull()
        ->and([$image->width, $image->height])->toBe([40, 30])
        ->and($image->mime)->toBe('image/gif')
        ->and($image->extension)->toBe('gif');
});

it('follows the configured width, format and quality', function (): void {
    config(['made-cms-package.media.image.max_width' => 100, 'made-cms-package.media.image.format' => 'jpg']);
    $path = photo(400, 300);

    config(['made-cms-package.media.image.quality' => 90]);
    $fine = scaled($path);
    config(['made-cms-package.media.image.quality' => 10]);
    $rough = scaled($path);

    expect(imageSize($fine->contents))->toBe([100, 75, 'mime' => 'image/jpeg'])
        ->and($fine->mime)->toBe('image/jpeg')
        ->and($fine->extension)->toBe('jpg')
        ->and(strlen((string) $rough->contents))->toBeLessThan(strlen((string) $fine->contents));
});

it('scales with Imagick and leaves the metadata of the camera out', function (): void {
    config(['made-cms-package.media.image.driver' => 'imagick']);

    $image = scaled(photo(2400, 1600, orientation: 1));

    expect(imageSize($image->contents))->toBe([1920, 1280, 'mime' => 'image/webp'])
        ->and((string) $image->contents)->not->toContain('Exif');
})->skip(! extension_loaded('imagick'), 'Needs ext-imagick.');

it('refuses a driver it does not know', function (): void {
    config(['made-cms-package.media.image.driver' => 'vips']);

    expect(fn (): ScaledImage => scaled(photo(10, 10)))
        ->toThrow(ImageDriverNotSupported::class, 'The image driver [vips] is not supported. Use gd or imagick.');
});

it('refuses a photo it cannot read', function (): void {
    $path = uploadable('kapot.jpg', "\xFF\xD8\xFF\xE0".str_repeat('kapot', 200));
    $checked = CheckUploadedMedia::run($path);

    expect($checked->kind)->toBe(MediaKind::Image)
        ->and(fn (): ScaledImage => ScaleImage::run($path, $checked))->toThrow(ValidationException::class, __('made-cms-package::media.errors.unreadable'));
});
