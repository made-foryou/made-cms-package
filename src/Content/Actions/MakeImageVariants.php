<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\Format;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\ScaledImage;
use Made\CmsPackage\Content\Enums\ImageDriver;
use Made\CmsPackage\Content\Exceptions\ImageDriverNotSupported;

final class MakeImageVariants
{
    use AsAction;

    /**
     * The narrower sizes of a photo, so a site offers a phone a small file in a srcset: one per width of
     * media.image.variants that is narrower than the photo (never as wide or wider), from narrow to wide, with the
     * proportions of the photo, written as media.image.format at media.image.quality without the metadata of the
     * camera. They are made from the photo the site shows, never from the original, so they show what a crop kept and
     * cost little: the photo is at most media.image.max_width wide already.
     *
     * @param  string  $photo  the contents of the photo the site shows
     * @return list<ScaledImage>
     *
     * @throws DecoderException when the contents are no image
     * @throws ImageDriverNotSupported
     */
    public function handle(string $photo): array
    {
        $image = ImageDriver::configured()->manager()->decodeBinary($photo);
        $format = Format::create(config()->string('made-cms-package.media.image.format'));
        $quality = config()->integer('made-cms-package.media.image.quality');
        $widths = array_filter(self::widths(), static fn (int $width): bool => $width < $image->width());
        $variants = [];

        // From wide to narrow on the one image, so a photo that was not scaled down (up to 16383 px) is never copied
        // in memory; each size is still made from a wider one.
        foreach (array_reverse($widths) as $width) {
            $image = $image->scaleDown(width: $width);
            $encoded = $image->encodeUsingFormat($format, quality: $quality);

            $variants[] = new ScaledImage(
                $image->width(),
                $image->height(),
                $encoded->toString(),
                $format->mediaType()->value,
                $format->fileExtension()->value,
            );
        }

        return array_reverse($variants);
    }

    /**
     * The widths of media.image.variants that are a width: whole numbers above zero, each once, from narrow to wide.
     *
     * @return list<int>
     */
    public static function widths(): array
    {
        $widths = array_filter(
            (array) config('made-cms-package.media.image.variants', []),
            static fn (mixed $width): bool => is_int($width) && $width > 0,
        );
        $widths = array_values(array_unique($widths));
        sort($widths);

        return $widths;
    }
}
