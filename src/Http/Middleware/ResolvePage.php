<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteMatch;
use Made\CmsPackage\Access\Actions\CheckScreenAccess;
use Made\CmsPackage\Access\Data\ScreenVisit;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Content\Actions\FindPage;
use Made\CmsPackage\Content\Actions\FindPublishedPage;
use Made\CmsPackage\Content\Data\EditMode;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Http\Launcher\Visitor;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns the path of a page route into the published page at that address, or answers 404.
 *
 * The route action receives the page in place of the path, so `fn (Page $page)` gets it whatever its parameter is called.
 *
 * With `?made-cms=edit` an editor who opens the pages screen gets the edit mode, for the frame of the CMS: the page
 * whatever its status, its working copy through RenderStrips, and a response that is never stored, may be framed by
 * the same origin and is not indexed. For anyone else the parameter changes nothing: a draft stays a 404.
 */
final class ResolvePage
{
    /**
     * The route parameter that holds the address. Not "page": implicit binding would then look the page up by id.
     */
    public const string PARAMETER = 'path';

    /**
     * The query parameter and value the CMS loads a page in its frame with.
     */
    public const string QUERY = 'made-cms';

    public const string EDIT = 'edit';

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

        $editing = $this->editing($request);

        if ($editing) {
            app()->instance(EditMode::class, EditMode::on());
        }

        $path = PagePath::fromString($path);
        $page = ($editing ? FindPage::run($path) : FindPublishedPage::run($path)) ?? abort(404);

        $route->setParameter(self::PARAMETER, $page);

        $response = $next($request);

        if ($editing) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            $response->setEtag(null);
        }

        return $response;
    }

    /**
     * Not in handle(): the launcher middleware around this one reads the edit mode after it returns.
     */
    public function terminate(Request $request, Response $response): void
    {
        app()->instance(EditMode::class, EditMode::off());
    }

    private function editing(Request $request): bool
    {
        return $request->query(self::QUERY) === self::EDIT
            && CheckScreenAccess::run(new ScreenVisit(editor: Visitor::editor($request), screen: Screen::Pages));
    }
}
