<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Made\CmsPackage\Access\Actions\ShowLauncher;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Responses\SessionResponse;

/**
 * Who is logged in to the CMS in this browser and what the launcher shows.
 */
final class SessionController
{
    public function __invoke(Request $request): JsonResponse
    {
        $visitor = Visitor::of($request);

        return SessionResponse::make($request, $visitor, ShowLauncher::run($visitor));
    }
}
