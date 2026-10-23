<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Access\Actions\LogInWithCode;
use Made\CmsPackage\Access\Data\CodeLogin;
use Made\CmsPackage\Access\Exceptions\LoginCodeRejected;
use Made\CmsPackage\Http\Responses\LoggedInResponse;
use Made\CmsPackage\Http\Session\LoginSession;

/**
 * Logs in with the code from the mail. A code login is always remembered: the design has no checkbox here.
 */
final class LoginCodeVerifyController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{code: string} $input */
        $input = $request->validate([
            'code' => ['required', 'string', 'digits:'.config()->integer('made-cms-package.login_code.length')],
        ], [
            'code.*' => __('made-cms-package::launcher.errors.code_incomplete'),
        ]);

        [$email, $binding] = LoginSession::of($request)->require();

        try {
            $result = LogInWithCode::run(new CodeLogin(email: $email, code: $input['code'], binding: $binding));
        } catch (LoginCodeRejected) {
            throw ValidationException::withMessages([
                'code' => __('made-cms-package::launcher.errors.code_invalid'),
            ]);
        }

        return LoggedInResponse::make($request, $result, remember: true);
    }
}
