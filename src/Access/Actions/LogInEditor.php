<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Enums\LoginMethod;
use Made\CmsPackage\Access\Events\EditorLoggedIn;
use Made\CmsPackage\Access\Models\Editor;

/**
 * Logs an editor in to the CMS guard. The guard renews the session id, so a session fixed before the login is worthless.
 */
final class LogInEditor
{
    use AsAction;

    public function __construct(
        private readonly AuthFactory $auth,
    ) {}

    public function handle(Editor $editor, LoginMethod $method, bool $remember): void
    {
        $guard = $this->auth->guard(config()->string('made-cms-package.auth.guard'));

        if (! $guard instanceof StatefulGuard) {
            throw new LogicException('The Made CMS guard must be a session guard.');
        }

        if ($guard instanceof SessionGuard) {
            $guard->setRememberDuration(config()->integer('made-cms-package.auth.remember_days') * 24 * 60);
        }

        $guard->login($editor, $remember);

        EditorLoggedIn::dispatch($editor, $method);
    }
}
