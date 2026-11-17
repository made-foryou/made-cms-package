<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

/**
 * A file of the media library is not on the media disk, or the disk does not give it.
 */
final class MediaFileMissing extends RuntimeException
{
    public static function at(string $path): self
    {
        return new self("The media disk does not give [{$path}].");
    }
}
