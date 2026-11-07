<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use RuntimeException;

/**
 * The scan writes code into the site, and only on the machine of a developer: at APP_ENV=local.
 */
final class NotLocalEnvironment extends RuntimeException
{
    public static function forWriting(): self
    {
        return new self('The scan writes into the site only at APP_ENV=local.');
    }
}
