<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Made\CmsPackage\Access\Actions\RequestLoginCode;
use Made\CmsPackage\Access\Data\LoginCodeRequest;
use Made\CmsPackage\Http\Responses\StepResponse;
use Made\CmsPackage\Http\Session\LoginSession;

/**
 * Sends a login code for the address of step 1. The answer is the same whether the address has an account or not.
 */
final class LoginCodeController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $session = LoginSession::of($request);
        [$email, $binding] = $session->require();

        RequestLoginCode::run(new LoginCodeRequest(
            email: $email,
            binding: $binding,
            returnTo: $this->returnTo($request->input('return_to')),
        ));

        return StepResponse::make($request, 'code', [
            'email' => $email,
            'minutes' => config()->integer('made-cms-package.login_code.ttl_minutes'),
        ]);
    }

    /**
     * Only a path on this site, so the mail link can never send the editor somewhere else.
     */
    private function returnTo(mixed $path): ?string
    {
        if (! is_string($path) || ! str_starts_with($path, '/') || str_starts_with($path, '//') || str_starts_with($path, '/\\')) {
            return null;
        }

        return $path;
    }
}
