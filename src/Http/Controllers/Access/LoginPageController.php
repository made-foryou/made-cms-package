<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Made\CmsPackage\Access\Actions\OpenLoginPage;
use Made\CmsPackage\Access\Data\LoginPageVisit;
use Made\CmsPackage\Http\Launcher\LoginPage;
use Made\CmsPackage\Http\Launcher\Visitor;

/**
 * The login page for a direct link (an invitation, a bookmark): the steps of the panel as a page of their own.
 */
final class LoginPageController
{
    public function __invoke(Request $request): Response
    {
        $editor = Visitor::editor($request);

        $step = OpenLoginPage::run(new LoginPageVisit(
            editor: $editor,
            arrived: $request->session()->get('made-cms-package.arrived') === true,
        ));

        return LoginPage::make($request, $step, $editor);
    }
}
