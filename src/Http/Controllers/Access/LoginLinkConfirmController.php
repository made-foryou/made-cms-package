<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Made\CmsPackage\Access\Actions\LogInWithLink;
use Made\CmsPackage\Access\Data\LinkLogin;
use Made\CmsPackage\Access\Exceptions\LoginLinkRejected;
use Made\CmsPackage\Http\Launcher\LinkPage;
use Made\CmsPackage\Http\Responses\LoggedInResponse;
use Made\CmsPackage\Http\Session\LoginSession;

/**
 * The button on the link page: logs in this browser, if it is the one that asked for the code.
 */
final class LoginLinkConfirmController
{
    public function __invoke(Request $request, string $token): RedirectResponse|Response
    {
        try {
            $login = LogInWithLink::run(new LinkLogin(
                token: $token,
                binding: LoginSession::of($request)->binding(),
            ));
        } catch (LoginLinkRejected $rejected) {
            return $rejected->otherBrowser
                ? LinkPage::make($request, 'other_browser', $token, 403)
                : LinkPage::make($request, 'expired', $token, 410);
        }

        return LoggedInResponse::redirect($request, $login->result, $login->returnTo ?? '/');
    }
}
