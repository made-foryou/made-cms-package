<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use Made\CmsPackage\Content\Models\Page;
use RuntimeException;

final class InvalidPagePosition extends RuntimeException
{
    public static function for(Page $page, int $position, int $max): self
    {
        return new self("Place [{$position}] is outside the siblings of page [{$page->id}]; use 0 to {$max}.");
    }
}
