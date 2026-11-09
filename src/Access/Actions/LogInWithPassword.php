<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
 * account without a password are rejected in the same way and in the same time: each checks exactly one hash.
 */
final class LogInWithPassword
{
    use AsAction;

    private const int MINIMUM_MICROSECONDS = 400_000;

    private const string STAND_IN_KEY = 'made-cms-package.stand-in-password-hash';

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

        // Exactly one check on every path, so an unknown address or an account without a password costs as much
        // as a wrong password, also when one check takes longer than the timebox.
        $valid = Hash::check($login->password, $hash ?? $this->standInHash());

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

    /**
     * A hash of a random password with the current hash settings, to check against when there is no real hash.
     * Kept in the cache, because making it costs as much as a check and a request under PHP-FPM forgets statics.
     * New hash settings make a new one.
     */
    private function standInHash(): string
    {
        $hash = Cache::get(self::STAND_IN_KEY);

        if (! is_string($hash) || Hash::needsRehash($hash)) {
            $hash = Hash::make(Str::random(40));

            Cache::forever(self::STAND_IN_KEY, $hash);
        }

        return $hash;
    }
}
