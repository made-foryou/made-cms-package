<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Data\LoginPageVisit;
use Made\CmsPackage\Access\Enums\LoginPageStep;

/**
 * Decides the step the login page opens on. The page is the door for a direct link, so unlike the tile
 * it opens for every visitor; logging in decides who gets in.
 */
final class OpenLoginPage
{
    use AsAction;

    public function handle(LoginPageVisit $visit): LoginPageStep
    {
        if ($visit->editor === null) {
            return LoginPageStep::Email;
        }

        // Just back from the link in the mail: first "you are in", then on to where the editor was.
        return $visit->arrived ? LoginPageStep::Done : LoginPageStep::Continue;
    }
}
