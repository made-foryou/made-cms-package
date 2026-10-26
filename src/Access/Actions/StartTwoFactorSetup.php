<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Data\TwoFactorSetup;
use Made\CmsPackage\Access\Data\TwoFactorSetupRequest;
use Made\CmsPackage\Access\Exceptions\TwoFactorSetupNotAllowed;
use Made\CmsPackage\Support\Site;
use PragmaRX\Google2FA\Google2FA;

/**
 * Creates a new authenticator secret. Only right after typing the password, so someone at an unlocked
 * computer cannot quietly add their own app; and never over a confirmed one.
 */
final class StartTwoFactorSetup
{
    use AsAction;

    private const int PASSWORD_VALID_SECONDS = 15 * 60;

    public function __construct(
        private readonly Google2FA $google2fa,
    ) {}

    public function handle(TwoFactorSetupRequest $request): TwoFactorSetup
    {
        $editor = $request->editor;

        if ($editor->two_factor_confirmed_at !== null) {
            throw TwoFactorSetupNotAllowed::alreadyEnabled($editor);
        }

        if ($request->passwordConfirmedAt === null || $request->passwordConfirmedAt < now()->getTimestamp() - self::PASSWORD_VALID_SECONDS) {
            throw TwoFactorSetupNotAllowed::passwordNotConfirmed($editor);
        }

        $secret = $this->google2fa->generateSecretKey(32);

        $editor->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_timestep' => null,
        ])->save();

        return new TwoFactorSetup(
            secret: $secret,
            otpauthUrl: $this->google2fa->getQRCodeUrl(Site::name(), $editor->email, $secret),
        );
    }
}
