<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Access\Actions\LogInWithPassword;
use Made\CmsPackage\Access\Exceptions\CredentialsRejected;
use Made\CmsPackage\Http\Requests\Access\PasswordLoginRequest;
use Made\CmsPackage\Http\Responses\PasswordLoginResponse;

/**
 * Logs in with a password, or continues to the second factor of an editor who turned it on.
 */
final class PasswordLoginController
{
    public function __invoke(PasswordLoginRequest $request): JsonResponse
    {
        $login = $request->toData();

        try {
            $result = LogInWithPassword::run($login);
        } catch (CredentialsRejected) {
            $request->reject('password', 'password_invalid');
        }

        return PasswordLoginResponse::make($request, $result, $login->remember);
    }
}
