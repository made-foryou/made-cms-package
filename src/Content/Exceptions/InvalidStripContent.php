<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

final class InvalidStripContent extends RuntimeException
{
    public static function because(string $type, string $path, string $reason): self
    {
        return new self($path === ''
            ? "Content of strip type [{$type}] is invalid: {$reason}."
            : "Content of strip type [{$type}] is invalid at [{$path}]: {$reason}.");
    }

    public static function unknownType(string $type): self
    {
        return new self("Strip type [{$type}] has no definition in the strips folder.");
    }
}
