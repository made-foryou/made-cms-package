<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Made\CmsPackage\Access\Actions\CheckScreenAccess;
use Made\CmsPackage\Access\Data\ScreenVisit;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Http\Launcher\Visitor;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a route of a CMS screen. A guest goes to the login page, an editor without a role for the screen gets a 403.
 */
final class EnsureScreenAccess
{
    /**
     * The middleware for a route of this screen: ->middleware(EnsureScreenAccess::using(Screen::Redirects)). With
     * more screens the route opens for an editor who opens one of them, such as uploading a file from the pages
     * (the edit shell) and from the media library: using(Screen::Pages, Screen::Media).
     */
    public static function using(Screen $screen, Screen ...$others): string
    {
        return self::class.':'.implode(',', array_map(static fn (Screen $screen): string => $screen->value, [$screen, ...$others]));
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$screens): Response
    {
        $editor = Visitor::editor($request);

        foreach ($screens as $screen) {
            if (CheckScreenAccess::run(new ScreenVisit(editor: $editor, screen: Screen::from($screen)))) {
                return $next($request);
            }
        }

        if ($editor === null) {
            abort_if($request->expectsJson(), Response::HTTP_UNAUTHORIZED);

            return redirect()->guest(route('made-cms-package.login'));
        }

        abort(Response::HTTP_FORBIDDEN);
    }
}
