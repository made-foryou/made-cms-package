<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Routing;

use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollectionInterface;
use Illuminate\Routing\Router;
use Made\CmsPackage\Content\Data\SitePrefixes;
use Made\CmsPackage\Http\Middleware\ResolvePage;

/**
 * What the routes of the site say about the CMS: the route groups that serve its pages, and the addresses the site
 * claims itself. Read from the routes, so it holds with `route:cache` too.
 */
final class SiteRoutes
{
    /**
     * The prefixes of the route groups that serve the pages with `Route::madeCms()` (the routes with `ResolvePage`),
     * the root first, then in the order of the routes.
     */
    public static function prefixes(Router $router): SitePrefixes
    {
        $prefixes = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if (in_array(ResolvePage::class, $route->middleware(), true)) {
                $prefixes[] = self::prefix($route);
            }
        }

        $prefixes = array_values(array_unique($prefixes));
        usort($prefixes, static fn (string $a, string $b): int => ($a !== '') <=> ($b !== ''));

        return new SitePrefixes($prefixes);
    }

    /**
     * The prefix of the route group of a route, without slashes: "" at the root.
     */
    public static function prefix(?Route $route): string
    {
        return trim((string) $route?->getPrefix(), '/');
    }

    /**
     * Whether the site has a GET route at this address already (with the prefix of its group, in the domain of the
     * innermost group being registered), which then stays its own.
     *
     * @param  array<mixed>  $groups  the group stack of the router
     */
    public static function claimed(RouteCollectionInterface $routes, array $groups, string $uri): bool
    {
        $group = $groups === [] ? null : end($groups);
        $domain = is_array($group) && is_string($group['domain'] ?? null) ? $group['domain'] : null;

        foreach ($routes->getRoutes() as $route) {
            if ($route->uri() === $uri && $route->getDomain() === $domain && in_array('GET', $route->methods(), true)) {
                return true;
            }
        }

        return false;
    }
}
