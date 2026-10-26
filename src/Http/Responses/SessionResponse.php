<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Made\CmsPackage\Access\Data\LauncherVisitor;
use Made\CmsPackage\Access\Enums\LauncherView;
use Made\CmsPackage\Http\Launcher\Visitor;

/**
 * Who is logged in to the CMS in this browser and what the launcher shows, with a fresh csrf token for its requests.
 * The token never sits in the injected page, so a page cache can never hand it to someone else.
 */
final class SessionResponse
{
    public static function make(Request $request, LauncherVisitor $visitor, LauncherView $view): JsonResponse
    {
        return new JsonResponse([
            'authenticated' => $visitor->editor !== null,
            'view' => $view->value,
            'editor' => Visitor::profile($visitor->editor),
            'csrf_token' => $request->session()->token(),
        ]);
    }
}
