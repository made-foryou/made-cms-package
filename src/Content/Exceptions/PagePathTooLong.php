<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use Made\CmsPackage\Content\Data\PagePath;
use RuntimeException;

final class PagePathTooLong extends RuntimeException
{
    public static function at(PagePath $path): self
    {
        return new self(sprintf('The address [%s] is longer than %d characters.', $path->value, PagePath::MAX_LENGTH));
    }
}
