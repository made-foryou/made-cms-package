<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Routing;

use Closure;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
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
     * @return Closure(Closure|string): Route
     */
    public function madeCms(): Closure
    {
        return function (Closure|string $responder): Route {
            return $this->get('{'.ResolvePage::PARAMETER.'?}', $responder)
                ->where(ResolvePage::PARAMETER, '.*')
                ->middleware(ResolvePage::class);
        };
    }
}
