<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Access\Actions\ConfirmTwoFactor;
use Made\CmsPackage\Access\Exceptions\TwoFactorCodeRejected;
use Made\CmsPackage\Http\Requests\Access\ConfirmTwoFactorRequest;
use Made\CmsPackage\Http\Responses\StepResponse;

/**
 * Turns the authenticator app on with its first code and shows the recovery codes, once.
 */
final class TwoFactorConfirmController
{
    public function __invoke(ConfirmTwoFactorRequest $request): JsonResponse
    {
        try {
            $codes = ConfirmTwoFactor::run($request->toData());
        } catch (TwoFactorCodeRejected) {
            $request->reject('code', 'totp_invalid');
        }

        return StepResponse::recoveryCodes($request, $codes);
    }
}
