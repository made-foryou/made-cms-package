<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Enums;

/**
 * Where a login attempt ended up.
 */
enum LoginStatus: string
{
    case LoggedIn = 'logged_in';
    case TwoFactorRequired = 'two_factor_required';
    case TwoFactorOffered = 'two_factor_offered';
}
