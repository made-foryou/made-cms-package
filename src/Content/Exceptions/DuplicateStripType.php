<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

final class DuplicateStripType extends RuntimeException
{
    public static function key(string $key, string $first, string $second): self
    {
        return new self("Strip types [{$first}] and [{$second}] share the key [{$key}].");
    }
}
