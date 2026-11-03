<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use Made\CmsPackage\Content\Models\Page;
use RuntimeException;

final class CircularPageMove extends RuntimeException
{
    public static function for(Page $page, Page $parent): self
    {
        return new self("Page [{$page->id}] cannot lie under page [{$parent->id}], which is the page itself or lies under it.");
    }
}
