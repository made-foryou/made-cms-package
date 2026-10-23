<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Made\CmsPackage\Access\Actions\LogInWithLink;
use Made\CmsPackage\Access\Exceptions\LoginLinkRejected;
use Made\CmsPackage\Http\Launcher\LinkPage;
use Made\CmsPackage\Http\Requests\Access\ConfirmLoginLinkRequest;
use Made\CmsPackage\Http\Responses\LoggedInResponse;

/**
 * The button on the link page: logs in this browser, if it is the one that asked for the code.
 */
final class LoginLinkConfirmController
{
    public function __invoke(ConfirmLoginLinkRequest $request): RedirectResponse|Response
    {
        try {
            $login = LogInWithLink::run($request->toData());
        } catch (LoginLinkRejected $rejected) {
            return LinkPage::rejected($request, $rejected, $request->token());
        }

        return LoggedInResponse::redirect($request, $login->result, $login->returnTo ?? '/');
    }
}
