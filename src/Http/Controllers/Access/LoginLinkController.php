<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Made\CmsPackage\Http\Launcher\LinkPage;

/**
 * Opening the link in the login mail only shows a button: mail scanners open links before the editor does,
 * and must never use the link up or log anyone in.
 */
final class LoginLinkController
{
    public function __invoke(Request $request, string $token): Response
    {
        return LinkPage::make($request, 'confirm', $token);
    }
}
