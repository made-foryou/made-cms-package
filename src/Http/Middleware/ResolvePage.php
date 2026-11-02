<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteMatch;
use Made\CmsPackage\Content\Actions\FindPublishedPage;
use Made\CmsPackage\Content\Data\PagePath;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns the path of a page route into the published page at that address, or answers 404.
 *
 * The route action receives the page in place of the path, so `fn (Page $page)` gets it whatever its parameter is called.
 */
final class ResolvePage
{
    /**
     * The route parameter that holds the address. Not "page": implicit binding would then look the page up by id.
     */
    public const string PARAMETER = 'path';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $path = $route instanceof RouteMatch ? $route->parameter(self::PARAMETER, '') : null;

        if (! $route instanceof RouteMatch || ! is_string($path)) {
            abort(404);
        }

        $page = FindPublishedPage::run(PagePath::fromString($path)) ?? abort(404);

        $route->setParameter(self::PARAMETER, $page);

        return $next($request);
    }
}
