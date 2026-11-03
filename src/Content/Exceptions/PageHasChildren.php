<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use Made\CmsPackage\Content\Models\Page;
use RuntimeException;

final class PageHasChildren extends RuntimeException
{
    public static function for(Page $page): self
    {
        return new self("Page [{$page->id}] still has pages under it; move or delete those first.");
    }
}
