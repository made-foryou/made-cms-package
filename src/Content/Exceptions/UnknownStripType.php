<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

final class UnknownStripType extends RuntimeException
{
    public static function key(string $key): self
    {
        return new self("Strip type [{$key}] is not in the catalogue.");
    }
}
