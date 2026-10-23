<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Access\Actions\StartTwoFactorSetup;
use Made\CmsPackage\Access\Data\TwoFactorSetupRequest;
use Made\CmsPackage\Access\Exceptions\TwoFactorSetupNotAllowed;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Responses\StepResponse;
use Made\CmsPackage\Http\Session\LoginSession;
use Made\CmsPackage\Support\QrCode;

/**
 * Starts adding an authenticator app, right after a password login.
 */
final class TwoFactorSetupController
{
    public function __invoke(Request $request): JsonResponse
    {
        $editor = Visitor::requireEditor($request);

        try {
            $setup = StartTwoFactorSetup::run(new TwoFactorSetupRequest(
                editor: $editor,
                passwordConfirmedAt: LoginSession::of($request)->passwordConfirmedAt(),
            ));
        } catch (TwoFactorSetupNotAllowed) {
            throw ValidationException::withMessages([
                'code' => __('made-cms-package::launcher.errors.setup_not_allowed'),
            ]);
        }

        return StepResponse::make($request, 'two_factor_setup', [
            'qr' => QrCode::svg($setup->otpauthUrl),
            'secret' => $setup->secret,
            'otpauth_url' => $setup->otpauthUrl,
        ]);
    }
}
