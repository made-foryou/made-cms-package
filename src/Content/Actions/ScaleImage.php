<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\CheckedMedia;
use Made\CmsPackage\Content\Data\ScaledImage;
use Made\CmsPackage\Content\Exceptions\ImageDriverNotSupported;

final class ScaleImage
{
    use AsAction;

    /**
     * The image as the site shows it: turned upright by its EXIF orientation, scaled down to media.image.max_width
     * (never up), without the metadata of the camera (such as where the photo was taken), and written as
     * media.image.format at media.image.quality. An animation stays as it is, so it keeps moving; only its size is
     * read. It runs in the request: GD needs about 65 MB for a photo of 4000 × 2667.
     *
     * @throws ValidationException keyed by "file" when the image cannot be read
     * @throws ImageDriverNotSupported
     */
    public function handle(string $path, CheckedMedia $media): ScaledImage
    {
        $driver = config()->string('made-cms-package.media.image.driver');

        $manager = ImageManager::usingDriver(match ($driver) {
            'gd' => GdDriver::class,
            'imagick' => ImagickDriver::class,
            default => throw ImageDriverNotSupported::named($driver),
        }, strip: true);

        try {
            $image = $manager->decodePath($path);
        } catch (DecoderException) {
            throw ValidationException::withMessages(['file' => (string) __('made-cms-package::media.errors.unreadable')]);
        }

        if ($image->isAnimated()) {
            return new ScaledImage($image->width(), $image->height(), null, $media->mime, $media->extension);
        }

        $format = Format::create(config()->string('made-cms-package.media.image.format'));
        $image = $image->scaleDown(width: config()->integer('made-cms-package.media.image.max_width'));
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
