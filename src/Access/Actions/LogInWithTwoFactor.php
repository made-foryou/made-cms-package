<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Data\LoginResult;
use Made\CmsPackage\Access\Data\TwoFactorLogin;
use Made\CmsPackage\Access\Enums\LoginMethod;
use Made\CmsPackage\Access\Enums\LoginStatus;
use Made\CmsPackage\Access\Exceptions\TwoFactorCodeRejected;
use Made\CmsPackage\Access\Models\Editor;

/**
 * Finishes a password login with a code from the authenticator app, or with a recovery code that then stops working.
 */
final class LogInWithTwoFactor
{
    use AsAction;

    public function handle(TwoFactorLogin $login): LoginResult
    {
        $editor = Editor::query()->find($login->editorId);

        if ($editor === null || $editor->two_factor_confirmed_at === null || ! $this->passes($editor, $login)) {
            throw TwoFactorCodeRejected::for($login->editorId);
        }

        LogInEditor::run($editor, LoginMethod::Password, $login->remember);

        return new LoginResult(LoginStatus::LoggedIn, $editor, LoginMethod::Password);
    }

    private function passes(Editor $editor, TwoFactorLogin $login): bool
    {
        if ($login->code !== null) {
            return VerifyTwoFactorCode::run($editor, $login->code);
        }

        return $login->recoveryCode !== null && $this->useRecoveryCode($editor, $login->recoveryCode);
    }

    private function useRecoveryCode(Editor $editor, string $typed): bool
    {
        $hash = hash('sha256', mb_strtolower(trim($typed)));
        $hashes = $editor->two_factor_recovery_codes ?? [];
        $remaining = array_values(array_filter($hashes, fn (string $stored): bool => ! hash_equals($stored, $hash)));

        if (count($remaining) === count($hashes)) {
            return false;
        }

        return Editor::query()
            ->whereKey($editor->id)
            ->withRecoveryCodes($hashes)
            ->update(['two_factor_recovery_codes' => json_encode($remaining)]) === 1;
    }
}
