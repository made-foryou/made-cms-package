<?php

declare(strict_types=1);

use Intervention\Image\Exceptions\DecoderException;
use Made\CmsPackage\Content\Actions\MakeImageVariants;
use Made\CmsPackage\Content\Data\ScaledImage;

/**
 * The width, height and type of each variant, as the bytes say.
 *
 * @param  list<ScaledImage>  $variants
 * @return list<array{0: int, 1: int, mime: string}>
 */
function variantSizes(array $variants): array
{
    return array_map(static function (ScaledImage $variant): array {
        $size = getimagesizefromstring((string) $variant->contents);

        expect($size)->toBeArray()
            ->and([$variant->width, $variant->height])->toBe([(int) $size[0], (int) $size[1]]);

        return [(int) $size[0], (int) $size[1], 'mime' => (string) $size['mime']];
    }, $variants);
}

it('makes the configured widths that are narrower than the photo, keeping its proportions', function (int $width, int $height, array $sizes): void {
    $variants = MakeImageVariants::run((string) file_get_contents(photo($width, $height, 'webp')));

    expect(variantSizes($variants))->toBe($sizes);
})->with([
    'the largest photo' => [1920, 1280, [[480, 320, 'mime' => 'image/webp'], [960, 640, 'mime' => 'image/webp'], [1440, 960, 'mime' => 'image/webp']]],
    'a portrait' => [1280, 1920, [[480, 720, 'mime' => 'image/webp'], [960, 1440, 'mime' => 'image/webp']]],
    'a width of its own' => [1000, 500, [[480, 240, 'mime' => 'image/webp'], [960, 480, 'mime' => 'image/webp']]],
    'exactly a configured width' => [960, 640, [[480, 320, 'mime' => 'image/webp']]],
    'narrower than every width' => [400, 300, []],
]);

it('takes the widths sorted and once, and nothing without widths', function (): void {
    $photo = (string) file_get_contents(photo(1920, 1280, 'webp'));

    config(['made-cms-package.media.image.variants' => [960, 480, 960, 0, -10]]);

    expect(array_map(static fn (ScaledImage $variant): int => $variant->width, MakeImageVariants::run($photo)))->toBe([480, 960]);

    config(['made-cms-package.media.image.variants' => []]);

    expect(MakeImageVariants::run($photo))->toBe([]);
});

it('follows the configured format and quality, without the metadata of the camera', function (): void {
    config(['made-cms-package.media.image.format' => 'jpg', 'made-cms-package.media.image.variants' => [480]]);
    $photo = (string) file_get_contents(photo(960, 720, 'jpg', orientation: 1));

    config(['made-cms-package.media.image.quality' => 90]);
    [$fine] = MakeImageVariants::run($photo);
    config(['made-cms-package.media.image.quality' => 10]);
    [$rough] = MakeImageVariants::run($photo);

    expect(variantSizes([$fine]))->toBe([[480, 360, 'mime' => 'image/jpeg']])
        ->and($fine->mime)->toBe('image/jpeg')
        ->and($fine->extension)->toBe('jpg')
        ->and((string) $fine->contents)->not->toContain('Exif')
        ->and(strlen((string) $rough->contents))->toBeLessThan(strlen((string) $fine->contents));
});

it('makes variants with Imagick', function (): void {
    config(['made-cms-package.media.image.driver' => 'imagick']);

    expect(variantSizes(MakeImageVariants::run((string) file_get_contents(photo(1000, 500, 'webp')))))
        ->toBe([[480, 240, 'mime' => 'image/webp'], [960, 480, 'mime' => 'image/webp']]);
})->skip(! extension_loaded('imagick'), 'Needs ext-imagick.');

it('throws for bytes that are no photo', function (): void {
    expect(fn (): array => MakeImageVariants::run('kapot'))->toThrow(DecoderException::class);
});
