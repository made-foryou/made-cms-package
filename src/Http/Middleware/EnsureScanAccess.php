<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Made\CmsPackage\Access\Actions\CheckScanAccess;
use Made\CmsPackage\Access\Data\ScanVisit;
use Made\CmsPackage\Http\Launcher\Visitor;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the routes of the scan, whatever its panel shows. A guest goes to the login page, anyone else who may not
 * scan (no admin, or not at APP_ENV=local) gets a 403.
 */
final class EnsureScanAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $editor = Visitor::editor($request);

        if (CheckScanAccess::run(new ScanVisit($editor))) {
            return $next($request);
        }

        if ($editor === null) {
            abort_if($request->expectsJson(), Response::HTTP_UNAUTHORIZED);

            return redirect()->guest(route('made-cms-package.login'));
        }

        abort(Response::HTTP_FORBIDDEN);
    }
}
