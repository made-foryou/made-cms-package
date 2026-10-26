<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Exceptions;

use RuntimeException;

final class TwoFactorCodeRejected extends RuntimeException
{
    public static function for(int $editorId): self
    {
        return new self("The authenticator or recovery code of editor [{$editorId}] was rejected.");
    }
}
