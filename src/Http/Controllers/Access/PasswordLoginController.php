<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Access\Actions\LogInWithPassword;
use Made\CmsPackage\Access\Data\PasswordLogin;
use Made\CmsPackage\Access\Enums\LoginStatus;
use Made\CmsPackage\Access\Exceptions\CredentialsRejected;
use Made\CmsPackage\Http\Responses\LoggedInResponse;
use Made\CmsPackage\Http\Responses\StepResponse;
use Made\CmsPackage\Http\Session\LoginSession;

/**
 * Logs in with a password, or continues to the second factor of an editor who turned it on.
 */
final class PasswordLoginController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{password: string} $input */
        $input = $request->validate([
            'password' => ['required', 'string', 'max:1024'],
            'remember' => ['sometimes', 'boolean'],
        ], [
            'password.*' => __('made-cms-package::launcher.errors.password_required'),
        ]);

        $session = LoginSession::of($request);
        [$email] = $session->require();
        $remember = $request->boolean('remember');

        try {
            $result = LogInWithPassword::run(new PasswordLogin(email: $email, password: $input['password'], remember: $remember));
        } catch (CredentialsRejected) {
            throw ValidationException::withMessages([
                'password' => __('made-cms-package::launcher.errors.password_invalid'),
            ]);
        }

        if ($result->status === LoginStatus::TwoFactorRequired) {
            // Half way through a login: a new session id, so nobody who knew the old one can finish it.
            $request->session()->regenerate();
            $session->awaitTwoFactor($result->editor->id, $remember);

            return StepResponse::make($request, 'two_factor');
        }

        $response = LoggedInResponse::make($request, $result, $remember, 'two_factor_offer');
        $session->confirmPassword();

        return $response;
    }
}
