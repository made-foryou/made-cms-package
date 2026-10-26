<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Data\LinkLogin;
use Made\CmsPackage\Access\Data\LinkLoginResult;
use Made\CmsPackage\Access\Data\LoginResult;
use Made\CmsPackage\Access\Enums\LoginMethod;
use Made\CmsPackage\Access\Enums\LoginStatus;
use Made\CmsPackage\Access\Exceptions\LoginLinkRejected;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Access\Models\LoginCode;

/**
 * Logs in through the link in the login mail, once, and only in the browser that asked for the code:
 * someone who starts a login with another person's address gains nothing when that person clicks.
 */
final class LogInWithLink
{
    use AsAction;

    public function handle(LinkLogin $login): LinkLoginResult
    {
        $loginCode = LoginCode::query()->forLink($login->token)->active()->first();

        if ($loginCode === null) {
            throw LoginLinkRejected::expired();
        }

        if ($login->binding === null || ! hash_equals($loginCode->session_hash, hash('sha256', $login->binding))) {
            throw LoginLinkRejected::otherBrowser();
        }

        $editor = Editor::query()->forEmail($loginCode->email)->first();

        if ($editor === null || LoginCode::query()->whereKey($loginCode->id)->active()->update(['consumed_at' => now()]) === 0) {
            throw LoginLinkRejected::expired();
        }

        LogInEditor::run($editor, LoginMethod::Code, true);

        return new LinkLoginResult(
            result: new LoginResult(LoginStatus::LoggedIn, $editor, LoginMethod::Code),
            returnTo: $loginCode->return_to,
        );
    }
}
