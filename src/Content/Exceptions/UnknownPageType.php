<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

final class UnknownPageType extends RuntimeException
{
    public static function key(string $key): self
    {
        return new self("Page type [{$key}] is not in the catalogue.");
    }
}
