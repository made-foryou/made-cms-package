<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Access\Actions\OfferLoginMethods;
use Made\CmsPackage\Http\Requests\Access\LoginEmailRequest;
use Made\CmsPackage\Http\Responses\StepResponse;
use Made\CmsPackage\Http\Session\LoginSession;

/**
 * Step 1: the e-mail address. Answers every address the same way, so the panel never tells who has an account.
 */
final class LoginEmailController
{
    public function __invoke(LoginEmailRequest $request): JsonResponse
    {
        $start = $request->toData();

        LoginSession::of($request)->start($start->email);

        return StepResponse::method($request, $start->email, OfferLoginMethods::run($start));
    }
}
