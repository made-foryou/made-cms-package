<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Data\RecoveryCodes;
use Made\CmsPackage\Access\Data\TwoFactorConfirmation;
use Made\CmsPackage\Access\Events\TwoFactorEnabled;
use Made\CmsPackage\Access\Exceptions\TwoFactorCodeRejected;

/**
 * Turns the second factor on once the app shows a valid code, with fresh recovery codes for a lost phone.
 */
final class ConfirmTwoFactor
{
    use AsAction;

    public function handle(TwoFactorConfirmation $confirmation): RecoveryCodes
    {
        $editor = $confirmation->editor;

        if ($editor->two_factor_confirmed_at !== null || ! VerifyTwoFactorCode::run($editor, $confirmation->code)) {
            throw TwoFactorCodeRejected::for($editor);
        }

        $codes = [];

        for ($i = 0; $i < config()->integer('made-cms-package.two_factor.recovery_codes'); $i++) {
            $codes[] = Str::lower(Str::random(10)).'-'.Str::lower(Str::random(10));
        }

        $editor->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $code): string => hash('sha256', $code), $codes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        TwoFactorEnabled::dispatch($editor);

        return new RecoveryCodes($codes);
    }
}
