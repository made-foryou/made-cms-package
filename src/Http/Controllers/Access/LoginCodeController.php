<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Access\Actions\RequestLoginCode;
use Made\CmsPackage\Http\Requests\Access\SendLoginCodeRequest;
use Made\CmsPackage\Http\Responses\StepResponse;

/**
 * Sends a login code for the address of step 1. The answer is the same whether the address has an account or not.
 */
final class LoginCodeController
{
    public function __invoke(SendLoginCodeRequest $request): JsonResponse
    {
        $code = $request->toData();

        RequestLoginCode::run($code);

        return StepResponse::code($request, $code->email);
    }
}
