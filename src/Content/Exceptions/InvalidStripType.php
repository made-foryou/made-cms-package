<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

final class InvalidStripType extends RuntimeException
{
    public static function because(string $key, string $reason): self
    {
        return new self("Strip type [{$key}] is invalid: {$reason}.");
    }
}
