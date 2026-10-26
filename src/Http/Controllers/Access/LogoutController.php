<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Made\CmsPackage\Access\Actions\LogOutEditor;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Responses\LoggedOutResponse;

/**
 * Logs out of the CMS only. The session itself stays, so a login to the host site and its cart survive.
 */
final class LogoutController
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $editor = Visitor::editor($request);

        if ($editor !== null) {
            LogOutEditor::run($editor);
        }

        return LoggedOutResponse::make($request);
    }
}
