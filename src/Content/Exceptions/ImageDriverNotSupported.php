<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

/**
 * The configured image driver is not one the package scales photos with: a mistake in the configuration of the site.
 */
final class ImageDriverNotSupported extends RuntimeException
{
    public static function named(string $driver): self
    {
        return new self("The image driver [{$driver}] is not supported. Use gd or imagick.");
    }
}
