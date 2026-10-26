<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Made\CmsPackage\Access\Data\LoginResult;
use Made\CmsPackage\Access\Enums\LoginStatus;
use Made\CmsPackage\Http\Session\LoginSession;

/**
 * After a right password: on to the authenticator code (still a guest), or logged in with a second factor on offer.
 */
final class PasswordLoginResponse
{
    public static function make(Request $request, LoginResult $result, bool $remember): JsonResponse
    {
        $session = LoginSession::of($request);

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
