<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Made\CmsPackage\Access\Actions\RememberDevice;
use Made\CmsPackage\Access\Data\LoginResult;
use Made\CmsPackage\Http\Cookies\EditorDevice;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Session\LoginSession;

/**
 * The answer after an editor logged in: a new csrf token, the login in progress forgotten
 * and the device remembered (when asked).
 */
final class LoggedInResponse
{
    /**
     * For the panel: on to the given step.
     */
    public static function make(Request $request, LoginResult $result, bool $remember, string $step = 'done'): JsonResponse
    {
        self::settle($request);

        $response = StepResponse::make($request, $step, [
            'editor' => Visitor::profile($result->editor),
        ]);

        return self::remember($response, $result, $remember);
    }

    /**
     * For the mail link: back to the page of the site, where the launcher shows the edit bar arriving.
     */
    public static function redirect(Request $request, LoginResult $result, string $to): RedirectResponse
    {
        self::settle($request);

        $response = redirect()->to($to)->with('made-cms-package.arrived', true);

        return self::remember($response, $result, true);
    }

    private static function settle(Request $request): void
    {
        $request->session()->regenerateToken();
        LoginSession::of($request)->forget();
    }

    /**
     * @template TResponse of JsonResponse|RedirectResponse
     *
     * @param  TResponse  $response
     * @return TResponse
     */
    private static function remember(JsonResponse|RedirectResponse $response, LoginResult $result, bool $remember): JsonResponse|RedirectResponse
    {
        if ($remember) {
            $response->headers->setCookie(EditorDevice::make(RememberDevice::run($result->editor, $result->method)));
        }

        return $response;
    }
}
