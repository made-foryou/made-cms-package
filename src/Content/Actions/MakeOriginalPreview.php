<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Validation\ValidationException;
use Intervention\Image\Format;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\ScaledImage;
use Made\CmsPackage\Content\Exceptions\ImageDriverNotSupported;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Exceptions\MediaNotCroppable;
use Made\CmsPackage\Content\Models\Media;

final class MakeOriginalPreview
{
    use AsAction;

    /**
     * The whole original of a photo for the crop step to draw the crop on: upright, scaled down to
     * media.image.max_width as media.image.format, without the metadata of the camera. It is made for each request and
     * never stored, so the uncropped photo and the place it was taken never get an address a visitor can open. Its
     * bytes per pixel let the step estimate the size of a crop.
     *
     * @throws MediaNotCroppable for a file without an original
     * @throws ValidationException keyed by "crop" when the original is not on the disk or cannot be read
     * @throws MediaDiskNotUsable
     * @throws ImageDriverNotSupported
     */
    public function handle(Media $media): ScaledImage
    {
        $image = DecodeOriginal::run($media);
        $width = $image->width();
        $height = $image->height();
        $format = Format::create(config()->string('made-cms-package.media.image.format'));
        $image = $image->scaleDown(width: config()->integer('made-cms-package.media.image.max_width'));
        $encoded = $image->encodeUsingFormat($format, quality: config()->integer('made-cms-package.media.image.quality'));

        return new ScaledImage(
            $image->width(),
            $image->height(),
            $encoded->toString(),
            $format->mediaType()->value,
            $format->fileExtension()->value,
            $width,
            $height,
        );
    }
}
