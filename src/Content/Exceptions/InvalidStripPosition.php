<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use Made\CmsPackage\Content\Models\Page;
use RuntimeException;

final class InvalidStripPosition extends RuntimeException
{
    public static function for(Page $page, int $position, int $max): self
    {
        return new self("Position [{$position}] is outside page [{$page->id}]; use 0 to {$max}.");
    }
}
