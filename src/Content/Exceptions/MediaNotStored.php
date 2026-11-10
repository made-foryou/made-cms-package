<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

/**
 * The media disk did not take a file, such as a full disk or a folder the app may not write to.
 */
final class MediaNotStored extends RuntimeException
{
    public static function at(string $path): self
    {
        return new self("The media disk did not store [{$path}].");
    }
}
