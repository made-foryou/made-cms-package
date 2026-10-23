<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Exceptions;

use Made\CmsPackage\Access\Models\Editor;
use RuntimeException;

final class TwoFactorCodeRejected extends RuntimeException
{
    public static function for(Editor $editor): self
    {
        return new self("The authenticator code of editor [{$editor->id}] was rejected.");
    }
}
