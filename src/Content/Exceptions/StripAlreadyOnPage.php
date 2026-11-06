<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use Made\CmsPackage\Content\Models\Page;
use RuntimeException;

final class StripAlreadyOnPage extends RuntimeException
{
    public static function id(Page $page, string $id): self
    {
        return new self("Page [{$page->id}] already holds strip [{$id}].");
    }
}
