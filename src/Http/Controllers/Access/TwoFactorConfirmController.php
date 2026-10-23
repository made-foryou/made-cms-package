<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Access\Actions\ConfirmTwoFactor;
use Made\CmsPackage\Access\Data\TwoFactorConfirmation;
use Made\CmsPackage\Access\Exceptions\TwoFactorCodeRejected;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Responses\StepResponse;

/**
 * Turns the authenticator app on with its first code and shows the recovery codes, once.
 */
final class TwoFactorConfirmController
{
    public function __invoke(Request $request): JsonResponse
    {
        $editor = Visitor::requireEditor($request);

        /** @var array{code: string} $input */
        $input = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ], [
            'code.*' => __('made-cms-package::launcher.errors.code_incomplete'),
        ]);

        try {
            $codes = ConfirmTwoFactor::run(new TwoFactorConfirmation($editor, $input['code']));
        } catch (TwoFactorCodeRejected) {
            throw ValidationException::withMessages([
                'code' => __('made-cms-package::launcher.errors.totp_invalid'),
            ]);
        }

        return StepResponse::make($request, 'recovery_codes', [
            'recovery_codes' => $codes->codes,
        ]);
    }
}
