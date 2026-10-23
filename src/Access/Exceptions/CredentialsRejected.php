<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Exceptions;

use RuntimeException;

/**
 * A wrong password, an unknown address or an account without a password: one exception, so nobody can tell them apart.
 */
final class CredentialsRejected extends RuntimeException
{
    public static function for(string $email): self
    {
        return new self("The credentials for [{$email}] were rejected.");
    }
}
