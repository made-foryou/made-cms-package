<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Models\Editor;
use PragmaRX\Google2FA\Google2FA;
use Throwable;

/**
 * Checks a code from the authenticator app. A code is accepted once: its time step is stored, and an
 * atomic update makes sure two requests with the same code can never both get in.
 */
final class VerifyTwoFactorCode
{
    use AsAction;

    public function __construct(
        private readonly Google2FA $google2fa,
    ) {}

    public function handle(Editor $editor, string $code): bool
    {
        $secret = $editor->two_factor_secret;

        if ($secret === null || preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        try {
            $timestep = $this->google2fa->verifyKeyNewer(
                $secret,
                $code,
                $editor->two_factor_last_used_timestep ?? 0,
                config()->integer('made-cms-package.two_factor.window'),
            );
        } catch (Throwable) {
            return false;
        }

        if (! is_int($timestep)) {
            return false;
        }

        $accepted = Editor::query()
            ->whereKey($editor->id)
            ->lastUsedTimestepBefore($timestep)
            ->update(['two_factor_last_used_timestep' => $timestep]) === 1;

        if ($accepted) {
            $editor->two_factor_last_used_timestep = $timestep;
            $editor->syncOriginalAttribute('two_factor_last_used_timestep');
        }

        return $accepted;
    }
}
