<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\PageVersion;
use RuntimeException;

final class PageVersionNotFound extends RuntimeException
{
    public static function on(Page $page, PageVersion $version): self
    {
        return new self("Version [{$version->id}] is not a version of page [{$page->id}].");
    }
}
