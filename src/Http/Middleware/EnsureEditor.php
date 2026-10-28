<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Made\CmsPackage\Http\Launcher\Visitor;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a CMS page that every editor opens, such as the start page. A guest goes to the login page.
 */
final class EnsureEditor
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Visitor::editor($request) !== null) {
            return $next($request);
        }

        abort_if($request->expectsJson(), Response::HTTP_UNAUTHORIZED);

        return redirect()->guest(route('made-cms-package.login'));
    }
}
