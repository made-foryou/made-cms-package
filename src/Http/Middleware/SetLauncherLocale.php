<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Made\CmsPackage\Support\Locale;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers the CMS requests in the language of the launcher, so validation messages match the panel.
 */
final class SetLauncherLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(Locale::launcher());

        return $next($request);
    }
}
