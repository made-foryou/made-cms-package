<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Made\CmsPackage\Access\Actions\OfferLoginMethods;
use Made\CmsPackage\Access\Data\LoginStart;
use Made\CmsPackage\Access\Enums\LoginMethod;
use Made\CmsPackage\Http\Cookies\EditorDevice;
use Made\CmsPackage\Http\Responses\StepResponse;
use Made\CmsPackage\Http\Session\LoginSession;

/**
 * Step 1: the e-mail address. Answers every address the same way, so the panel never tells who has an account.
 */
final class LoginEmailController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{email: string} $input */
        $input = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [
            'email.required' => __('made-cms-package::launcher.errors.email_required'),
            'email.*' => __('made-cms-package::launcher.errors.email_invalid'),
        ]);

        $session = LoginSession::of($request);
        $session->start($input['email']);

        $options = OfferLoginMethods::run(new LoginStart(
            email: $input['email'],
            device: EditorDevice::read($request),
        ));

        return StepResponse::make($request, 'method', [
            'email' => $session->email(),
            'name' => $options->firstName,
            'preferred' => $options->preferred?->value,
            'methods' => array_map(fn (LoginMethod $method): string => $method->value, $options->methods),
        ]);
    }
}
