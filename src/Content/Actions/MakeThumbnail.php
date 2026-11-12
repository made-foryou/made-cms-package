<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\Format;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\ScaledImage;
use Made\CmsPackage\Content\Enums\ImageDriver;
use Made\CmsPackage\Content\Exceptions\ImageDriverNotSupported;

final class MakeThumbnail
{
    use AsAction;

    /** The width of the area a thumbnail covers: a tile of the library on a sharp screen. */
    public const int WIDTH = 480;

    /** The height of the area a thumbnail covers. */
    public const int HEIGHT = 360;

    /**
     * The thumbnail of a photo, for the tiles of the library and the picker: the photo as the site shows it, scaled
     * down until it just covers WIDTH × HEIGHT with its own proportions (never up, never cropped, so the focal point
     * still places it in a tile), written as media.image.format at media.image.quality without the metadata of the
     * camera. It is made from the photo the site shows, never from the original, so it shows what a crop kept.
     *
     * @param  string  $photo  the contents of the photo the site shows
     *
     * @throws DecoderException when the contents are no image
     * @throws ImageDriverNotSupported
     */
    public function handle(string $photo): ScaledImage
    {
        $image = ImageDriver::configured()->manager()->decodeBinary($photo);
        $scale = min(1, max(self::WIDTH / $image->width(), self::HEIGHT / $image->height()));

        $format = Format::create(config()->string('made-cms-package.media.image.format'));
        $image = $image->scaleDown(width: (int) ceil($image->width() * $scale));
        $encoded = $image->encodeUsingFormat($format, quality: config()->integer('made-cms-package.media.image.quality'));

        return new ScaledImage(
            $image->width(),
            $image->height(),
            $encoded->toString(),
            $format->mediaType()->value,
            $format->fileExtension()->value,
        );
    }
}
