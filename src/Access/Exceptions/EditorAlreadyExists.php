<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Exceptions;

use RuntimeException;

final class EditorAlreadyExists extends RuntimeException
{
    public static function for(string $email): self
    {
        return new self("An editor with the e-mail address [{$email}] already exists.");
    }
}
