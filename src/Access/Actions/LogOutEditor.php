<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Events\EditorLoggedOut;
use Made\CmsPackage\Access\Models\Editor;

/**
 * Logs the editor out of the CMS guard only: whoever is logged in to the host site stays logged in.
 */
final class LogOutEditor
{
    use AsAction;

    public function __construct(
        private readonly AuthFactory $auth,
    ) {}

    public function handle(Editor $editor): void
    {
        $guard = $this->auth->guard(config()->string('made-cms-package.auth.guard'));

        if (! $guard instanceof StatefulGuard) {
            throw new LogicException('The Made CMS guard must be a session guard.');
        }

        $guard->logout();

        EditorLoggedOut::dispatch($editor);
    }
}
