<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use Made\CmsPackage\Content\Models\Page;
use RuntimeException;

final class StripTypeNotAllowed extends RuntimeException
{
    public static function on(Page $page, string $stripType): self
    {
        return new self("Page [{$page->id}] of the type [{$page->type}] does not take a strip of the type [{$stripType}].");
    }
}
