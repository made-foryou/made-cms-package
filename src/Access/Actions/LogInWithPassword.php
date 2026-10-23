<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Data\LoginResult;
use Made\CmsPackage\Access\Data\PasswordLogin;
use Made\CmsPackage\Access\Enums\LoginMethod;
use Made\CmsPackage\Access\Enums\LoginStatus;
use Made\CmsPackage\Access\Exceptions\CredentialsRejected;
use Made\CmsPackage\Access\Models\Editor;

/**
 * Checks a password. An editor with an authenticator app still has to enter its code and is not logged in yet;
 * every other editor is logged in and offered a second factor. A wrong password, an unknown address and an
 * account without a password are rejected in the same way and in the same time.
 */
final class LogInWithPassword
{
    use AsAction;

    private const int MINIMUM_MICROSECONDS = 400_000;

    public function __construct(
        private readonly Timebox $timebox,
    ) {}

    public function handle(PasswordLogin $login): LoginResult
    {
        return $this->timebox->call(fn (): LoginResult => $this->attempt($login), self::MINIMUM_MICROSECONDS);
    }

    private function attempt(PasswordLogin $login): LoginResult
    {
        $editor = Editor::query()->forEmail($login->email)->first();
        $hash = $editor?->password;

        // Always check a hash, so an unknown address costs as much as a known one.
        $valid = Hash::check($login->password, $hash ?? Hash::make('made-cms-package-dummy'));

        if ($editor === null || $hash === null || $login->password === '' || ! $valid) {
            throw CredentialsRejected::for($login->email);
        }

        if (config()->boolean('hashing.rehash_on_login', true) && Hash::needsRehash($hash)) {
            $editor->forceFill(['password' => $login->password])->save();
        }

        if ($editor->two_factor_confirmed_at !== null) {
            return new LoginResult(LoginStatus::TwoFactorRequired, $editor, LoginMethod::Password);
        }

        LogInEditor::run($editor, LoginMethod::Password, $login->remember);

        return new LoginResult(LoginStatus::TwoFactorOffered, $editor, LoginMethod::Password);
    }
}
