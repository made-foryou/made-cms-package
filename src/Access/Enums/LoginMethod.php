<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Enums;

/**
 * The ways an editor can log in. Passkeys follow once they work (issue #4).
 */
enum LoginMethod: string
{
    case Code = 'code';
    case Password = 'password';
}
