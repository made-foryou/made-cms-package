<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Made\CmsPackage\Access\Actions\ShowLauncher;
use Made\CmsPackage\Http\Launcher\Visitor;

/**
 * Who is logged in to the CMS in this browser and what the launcher shows, with a fresh csrf token for its requests.
 * The token never sits in the injected page, so a page cache can never hand it to someone else.
 */
final class SessionController
{
    public function __invoke(Request $request): JsonResponse
    {
        $visitor = Visitor::of($request);

        return new JsonResponse([
            'authenticated' => $visitor->editor !== null,
            'view' => ShowLauncher::run($visitor)->value,
            'editor' => Visitor::profile($visitor->editor),
            'csrf_token' => $request->session()->token(),
        ]);
    }
}
