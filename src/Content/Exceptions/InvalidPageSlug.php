<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

final class InvalidPageSlug extends RuntimeException
{
    public static function for(string $raw): self
    {
        return new self("Nothing of [{$raw}] remains as the address of a page.");
    }
}
