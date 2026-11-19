<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Routing;

use Closure;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Made\CmsPackage\Http\Controllers\Content\ShowRobotsController;
use Made\CmsPackage\Http\Controllers\Content\ShowSitemapController;
use Made\CmsPackage\Http\Middleware\ResolvePage;

/**
 * The route macros the package adds to the router.
 *
 * @mixin Router
 */
final class RouterMixin
{
    /**
     * `Route::madeCms($responder)`: serves the published pages of the CMS where the site puts this line, as the last of its web routes.
     *
     * Every GET address that no route above it claims becomes a page lookup: the responder of the site (a closure or an
     * invokable class) receives the page and returns the response, a missing or unpublished page answers 404.
     * The route has no name, so the macro can be used more than once and survives `route:cache`; the site may add one.
     *
     * Before it come the sitemap of the group (`sitemap.xml`) and, in a group without a prefix, `robots.txt`: robots
     * only read the one at the root. A route the site has at either address already stays the site's own.
     *
     * @return Closure(Closure|string): Route
     */
    public function madeCms(): Closure
    {
        return function (Closure|string $responder): Route {
            $prefix = trim($this->getLastGroupPrefix(), '/');
            $files = ['sitemap.xml' => ShowSitemapController::class];

            if ($prefix === '') {
                $files['robots.txt'] = ShowRobotsController::class;
            }

            foreach ($files as $file => $controller) {
                $uri = ltrim($prefix.'/'.$file, '/');

                if (! SiteRoutes::claimed($this->getRoutes(), $this->getGroupStack(), $uri)) {
                    $this->get($file, $controller);
                }
            }

            return $this->get('{'.ResolvePage::PARAMETER.'?}', $responder)
                ->where(ResolvePage::PARAMETER, '.*')
                ->middleware(ResolvePage::class);
        };
    }
}
