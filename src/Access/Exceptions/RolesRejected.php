<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Exceptions;

use RuntimeException;

final class RolesRejected extends RuntimeException
{
    public static function empty(): self
    {
        return new self('An editor needs at least one role.');
    }

    public static function notAllowed(): self
    {
        return new self('This editor may not change these roles.');
    }

    public static function lastAdmin(): self
    {
        return new self('The last admin keeps the admin role.');
    }
}
