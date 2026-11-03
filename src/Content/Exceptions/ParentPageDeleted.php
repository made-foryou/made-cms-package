<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use Made\CmsPackage\Content\Models\Page;
use RuntimeException;

final class ParentPageDeleted extends RuntimeException
{
    public static function for(Page $parent): self
    {
        return new self("Page [{$parent->id}] is deleted; restore it first.");
    }
}
