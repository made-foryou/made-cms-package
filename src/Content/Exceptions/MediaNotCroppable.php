<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

/**
 * A file that cannot be cropped: only a photo that was scaled down keeps an original to cut from, so an svg, an
 * animated gif, a document and a video are kept as they came in.
 */
final class MediaNotCroppable extends RuntimeException
{
    public static function id(int $id): self
    {
        return new self("The file [{$id}] of the media library has no original to crop.");
    }
}
