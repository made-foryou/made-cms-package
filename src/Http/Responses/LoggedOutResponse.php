<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Made\CmsPackage\Access\Actions\ShowLauncher;
use Made\CmsPackage\Http\Launcher\Visitor;

/**
 * After logging out: a new csrf token and what the launcher shows now; without javascript back to the page.
 */
final class LoggedOutResponse
{
    public static function make(Request $request): JsonResponse|RedirectResponse
    {
        $request->session()->regenerateToken();

        if (! $request->expectsJson()) {
            return redirect()->back();
        }

        $visitor = Visitor::of($request);

        return SessionResponse::make($request, $visitor, ShowLauncher::run($visitor));
    }
}
