<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Access;

use Made\CmsPackage\Access\Data\TwoFactorSetupRequest;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * Adding an authenticator app, by the editor who is logged in, with when they last typed their password.
 */
final class StartTwoFactorSetupRequest extends LauncherRequest
{
    public function toData(): TwoFactorSetupRequest
    {
        return new TwoFactorSetupRequest(
            editor: Visitor::requireEditor($this),
            passwordConfirmedAt: $this->login()->passwordConfirmedAt(),
        );
    }
}
