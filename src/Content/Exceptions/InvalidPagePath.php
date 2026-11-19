<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

/**
 * The message leaves the address out: it may hold a NUL byte or bytes that are no text.
 */
final class InvalidPagePath extends RuntimeException
{
    public static function make(): self
    {
        return new self('An address holds a control character or is no UTF-8.');
    }
}
