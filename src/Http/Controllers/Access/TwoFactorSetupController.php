<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Access\Actions\StartTwoFactorSetup;
use Made\CmsPackage\Access\Exceptions\TwoFactorSetupNotAllowed;
use Made\CmsPackage\Http\Requests\Access\StartTwoFactorSetupRequest;
use Made\CmsPackage\Http\Responses\StepResponse;

/**
 * Starts adding an authenticator app, right after a password login.
 */
final class TwoFactorSetupController
{
    public function __invoke(StartTwoFactorSetupRequest $request): JsonResponse
    {
        try {
            $setup = StartTwoFactorSetup::run($request->toData());
        } catch (TwoFactorSetupNotAllowed) {
            $request->reject('code', 'setup_not_allowed');
        }

        return StepResponse::twoFactorSetup($request, $setup);
    }
}
