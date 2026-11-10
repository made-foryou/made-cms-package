<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

final class MediaFolderNotFound extends RuntimeException
{
    public static function id(int $id): self
    {
        return new self("There is no media folder [{$id}].");
    }
}
