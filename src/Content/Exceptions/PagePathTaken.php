<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use Made\CmsPackage\Content\Data\PagePath;
use RuntimeException;

final class PagePathTaken extends RuntimeException
{
    public static function at(PagePath $path): self
    {
        return new self("There is already a page at [{$path->value}].");
    }
}
