<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Enums;

/**
 * The step the login page opens on.
 */
enum LoginPageStep: string
{
    case Email = 'email';
    case Done = 'done';
    case Continue = 'continue';
}
