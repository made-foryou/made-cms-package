<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Exceptions;

use RuntimeException;

/**
 * One exception for every reason a code fails (wrong, expired, used, other browser, unknown address),
 * so the answer never tells which one it was.
 */
final class LoginCodeRejected extends RuntimeException
{
    public static function for(string $email): self
    {
        return new self("The login code for [{$email}] was rejected.");
    }
}
