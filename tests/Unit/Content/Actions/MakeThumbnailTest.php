<?php

declare(strict_types=1);

use Intervention\Image\Exceptions\DecoderException;
use Made\CmsPackage\Content\Actions\MakeThumbnail;
use Made\CmsPackage\Content\Data\ScaledImage;
use Made\CmsPackage\Content\Exceptions\ImageDriverNotSupported;

/**
 * The bytes of a photo as the site shows it.
 */
function shownPhoto(int $width, int $height, string $format = 'webp', ?int $orientation = null): string
{
    return (string) file_get_contents(photo($width, $height, $format, orientation: $orientation));
}

/**
 * @return array{0: int, 1: int, mime: string}
 */
function thumbnailSize(?string $contents): array
{
    $size = getimagesizefromstring((string) $contents);

    expect($size)->toBeArray();

    return [(int) $size[0], (int) $size[1], 'mime' => (string) $size['mime']];
}

it('scales a photo down until it just covers a tile of 480 × 360, keeping its proportions', function (int $width, int $height, array $thumbnail): void {
    $image = MakeThumbnail::run(shownPhoto($width, $height));

    expect([$image->width, $image->height])->toBe($thumbnail)
        ->and($image->mime)->toBe('image/webp')
        ->and($image->extension)->toBe('webp')
        ->and(thumbnailSize($image->contents))->toBe([...$thumbnail, 'mime' => 'image/webp']);
})->with([
    'landscape' => [1920, 1280, [540, 360]],
    'portrait' => [1280, 1920, [480, 720]],
    'panorama' => [1920, 640, [1080, 360]],
    'the tile itself' => [960, 720, [480, 360]],
    'a width that does not divide' => [4000, 2667, [540, 360]],
]);

it('never makes a small photo larger', function (int $width, int $height): void {
    $image = MakeThumbnail::run(shownPhoto($width, $height));

    expect(thumbnailSize($image->contents))->toBe([$width, $height, 'mime' => 'image/webp']);
})->with([
    'smaller than the tile' => [400, 300],
    'narrower only' => [300, 900],
]);

it('reads any photo the site shows', function (string $format): void {
    expect(thumbnailSize(MakeThumbnail::run(shownPhoto(960, 720, $format))->contents))->toBe([480, 360, 'mime' => 'image/webp']);
})->with(['webp', 'jpg', 'png', 'gif']);

it('follows the configured format and quality', function (): void {
    config(['made-cms-package.media.image.format' => 'jpg']);
    $photo = shownPhoto(960, 720);

    config(['made-cms-package.media.image.quality' => 90]);
    $fine = MakeThumbnail::run($photo);
    config(['made-cms-package.media.image.quality' => 10]);
    $rough = MakeThumbnail::run($photo);

    expect(thumbnailSize($fine->contents))->toBe([480, 360, 'mime' => 'image/jpeg'])
        ->and($fine->mime)->toBe('image/jpeg')
        ->and($fine->extension)->toBe('jpg')
        ->and(strlen((string) $rough->contents))->toBeLessThan(strlen((string) $fine->contents));
});

it('leaves the metadata of the camera out', function (): void {
    $image = MakeThumbnail::run(shownPhoto(960, 720, 'jpg', orientation: 1));

    expect((string) $image->contents)->not->toContain('Exif');
});

it('makes a thumbnail with Imagick', function (): void {
    config(['made-cms-package.media.image.driver' => 'imagick']);

    expect(thumbnailSize(MakeThumbnail::run(shownPhoto(1920, 1280))->contents))->toBe([540, 360, 'mime' => 'image/webp']);
})->skip(! extension_loaded('imagick'), 'Needs ext-imagick.');

it('refuses a driver it does not know', function (): void {
    config(['made-cms-package.media.image.driver' => 'vips']);

    expect(fn (): ScaledImage => MakeThumbnail::run(shownPhoto(10, 10)))
        ->toThrow(ImageDriverNotSupported::class, 'The image driver [vips] is not supported. Use gd or imagick.');
});

it('throws for bytes that are no photo', function (): void {
    expect(fn (): ScaledImage => MakeThumbnail::run('kapot'))->toThrow(DecoderException::class);
});
