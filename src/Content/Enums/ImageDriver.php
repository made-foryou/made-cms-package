<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Made\CmsPackage\Content\Exceptions\ImageDriverNotSupported;

/**
 * The image library the package reads and scales photos with (media.image.driver): GD, or Imagick when the host has it.
 */
enum ImageDriver: string
{
    case Gd = 'gd';
    case Imagick = 'imagick';

    /**
     * The driver the site configured.
     *
     * @throws ImageDriverNotSupported
     */
    public static function configured(): self
    {
        $driver = config()->string('made-cms-package.media.image.driver');

        return self::tryFrom($driver) ?? throw ImageDriverNotSupported::named($driver);
    }

    /**
     * Reads and writes images with this driver, leaving the metadata of the camera (such as where a photo was taken)
     * out of everything it writes.
     */
    public function manager(): ImageManagerInterface
    {
        return ImageManager::usingDriver(match ($this) {
            self::Gd => GdDriver::class,
            self::Imagick => ImagickDriver::class,
        }, strip: true);
    }
}
