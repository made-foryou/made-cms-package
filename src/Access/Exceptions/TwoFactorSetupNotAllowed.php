<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Exceptions;

use Made\CmsPackage\Access\Models\Editor;
use RuntimeException;

final class TwoFactorSetupNotAllowed extends RuntimeException
{
    public static function alreadyEnabled(Editor $editor): self
    {
        return new self("Editor [{$editor->id}] already confirmed an authenticator app.");
    }

    public static function passwordNotConfirmed(Editor $editor): self
    {
        return new self("Editor [{$editor->id}] has to type the password again before adding an authenticator app.");
    }
}
