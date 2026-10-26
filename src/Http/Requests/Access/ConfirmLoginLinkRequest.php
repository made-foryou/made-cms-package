<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Access;

use Made\CmsPackage\Access\Data\LinkLogin;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The button behind the link in the mail, in a browser that may or may not be the one that asked for the code.
 */
final class ConfirmLoginLinkRequest extends LauncherRequest
{
    public function token(): string
    {
        $token = $this->route('token');

        return is_string($token) ? $token : '';
    }

    public function toData(): LinkLogin
    {
        return new LinkLogin(token: $this->token(), binding: $this->login()->binding());
    }
}
