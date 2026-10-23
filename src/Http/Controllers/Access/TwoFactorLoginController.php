<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Access\Actions\LogInWithTwoFactor;
use Made\CmsPackage\Access\Data\TwoFactorLogin;
use Made\CmsPackage\Access\Exceptions\TwoFactorCodeRejected;
use Made\CmsPackage\Http\Responses\LoggedInResponse;
use Made\CmsPackage\Http\Session\LoginSession;

/**
 * The second step after a right password: a code from the authenticator app or a recovery code.
 */
final class TwoFactorLoginController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required_without:recovery_code', 'nullable', 'string', 'digits:6'],
            'recovery_code' => ['required_without:code', 'nullable', 'string', 'max:64'],
        ], [
            'code.*' => __('made-cms-package::launcher.errors.code_incomplete'),
            'recovery_code.*' => __('made-cms-package::launcher.errors.recovery_invalid'),
        ]);

        $pending = LoginSession::of($request)->pendingTwoFactor() ?? throw ValidationException::withMessages([
            'email' => __('made-cms-package::launcher.errors.expired'),
        ]);

        $code = $request->string('code')->toString();
        $recoveryCode = $request->string('recovery_code')->toString();

        try {
            $result = LogInWithTwoFactor::run(new TwoFactorLogin(
                editorId: $pending['editor'],
                code: $code === '' ? null : $code,
                recoveryCode: $recoveryCode === '' ? null : $recoveryCode,
                remember: $pending['remember'],
            ));
        } catch (TwoFactorCodeRejected) {
            throw ValidationException::withMessages($code !== ''
                ? ['code' => __('made-cms-package::launcher.errors.totp_invalid')]
                : ['recovery_code' => __('made-cms-package::launcher.errors.recovery_invalid')]);
        }

        return LoggedInResponse::make($request, $result, $pending['remember']);
    }
}
