<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Access\Actions\LogInWithTwoFactor;
use Made\CmsPackage\Access\Exceptions\TwoFactorCodeRejected;
use Made\CmsPackage\Http\Requests\Access\TwoFactorLoginRequest;
use Made\CmsPackage\Http\Responses\LoggedInResponse;

/**
 * The second step after a right password: a code from the authenticator app or a recovery code.
 */
final class TwoFactorLoginController
{
    public function __invoke(TwoFactorLoginRequest $request): JsonResponse
    {
        $login = $request->toData();

        try {
            $result = LogInWithTwoFactor::run($login);
        } catch (TwoFactorCodeRejected) {
            $request->rejectCode();
        }

        return LoggedInResponse::make($request, $result, $login->remember);
    }
}
