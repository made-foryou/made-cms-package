<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use Made\CmsPackage\Content\Models\Page;
use RuntimeException;

final class HomePageProtected extends RuntimeException
{
    public static function move(Page $page): self
    {
        return new self("The home page [{$page->id}] stays at the top of the site.");
    }

    public static function rename(Page $page): self
    {
        return new self("The home page [{$page->id}] keeps its address.");
    }

    public static function hide(Page $page): self
    {
        return new self("The home page [{$page->id}] stays visible to visitors.");
    }

    public static function delete(Page $page): self
    {
        return new self("The home page [{$page->id}] cannot be deleted.");
    }
}
