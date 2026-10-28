<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

final class InvalidPageImport extends RuntimeException
{
    public static function because(string $file, string $reason): self
    {
        return new self("Page file [{$file}] is invalid: {$reason}.");
    }
}
