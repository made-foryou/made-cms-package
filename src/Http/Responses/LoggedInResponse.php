<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Made\CmsPackage\Access\Actions\RememberDevice;
use Made\CmsPackage\Access\Data\LoginResult;
use Made\CmsPackage\Http\Cookies\EditorDevice;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Session\LoginSession;

/**
 * The answer after an editor logged in: a new csrf token, the login in progress forgotten,
 * the device remembered (when asked) and the panel on to the given step.
 */
final class LoggedInResponse
{
    public static function make(Request $request, LoginResult $result, bool $remember, string $step = 'done'): JsonResponse
    {
        $request->session()->regenerateToken();
        LoginSession::of($request)->forget();

        $response = StepResponse::make($request, $step, [
            'editor' => Visitor::profile($result->editor),
        ]);

        if ($remember) {
            $response->headers->setCookie(EditorDevice::make(RememberDevice::run($result->editor, $result->method)));
        }

        return $response;
    }
}
