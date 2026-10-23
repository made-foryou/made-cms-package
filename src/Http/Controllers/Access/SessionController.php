<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Made\CmsPackage\Access\Actions\DescribeEditor;
use Made\CmsPackage\Access\Models\Editor;

/**
 * Who is logged in to the CMS in this browser, with a fresh csrf token for the launcher's requests.
 * The token never sits in the injected page, so a page cache can never hand it to someone else.
 */
final class SessionController
{
    public function __invoke(Request $request): JsonResponse
    {
        $editor = $request->user(config()->string('made-cms-package.auth.guard'));
        $profile = $editor instanceof Editor ? DescribeEditor::run($editor) : null;

        return new JsonResponse([
            'authenticated' => $profile !== null,
            'editor' => $profile === null ? null : [
                'first_name' => $profile->firstName,
                'initial' => $profile->initial,
            ],
            'csrf_token' => $request->session()->token(),
        ]);
    }
}
