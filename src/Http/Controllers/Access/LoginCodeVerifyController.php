<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Access\Actions\LogInWithCode;
use Made\CmsPackage\Access\Exceptions\LoginCodeRejected;
use Made\CmsPackage\Http\Requests\Access\VerifyLoginCodeRequest;
use Made\CmsPackage\Http\Responses\LoggedInResponse;

/**
 * Logs in with the code from the mail. A code login is always remembered: the design has no checkbox here.
 */
final class LoginCodeVerifyController
{
    public function __invoke(VerifyLoginCodeRequest $request): JsonResponse
    {
        try {
            $result = LogInWithCode::run($request->toData());
        } catch (LoginCodeRejected) {
            $request->reject('code', 'code_invalid');
        }

        return LoggedInResponse::make($request, $result, remember: true);
    }
}
