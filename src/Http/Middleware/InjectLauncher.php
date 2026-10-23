<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Middleware;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Support\Str;
use Made\CmsPackage\Access\Actions\ShowLauncher;
use Made\CmsPackage\Access\Enums\LauncherView;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;
use Made\CmsPackage\Http\Cookies\EditorDevice;
use Made\CmsPackage\Http\Launcher\Endpoints;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Support\Locale;
use Made\CmsPackage\Support\Site;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Places the MADE launcher tile on every full html page of the host's web routes.
 */
final class InjectLauncher
{
    /**
     * The encrypted cookie that marks a device of an editor who logged in to the CMS before.
     */
    public const string COOKIE = EditorDevice::NAME;

    private const string MARKER = '<made-cms-launcher';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if ($this->isFullPage($request, $response)) {
                $visitor = Visitor::of($request);
                $view = ShowLauncher::run($visitor);

                if ($view !== LauncherView::Hidden) {
                    $this->inject($request, $response, $view, $visitor->editor);
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return $response;
    }

    private function isFullPage(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $request->hasHeader('X-Inertia') || $request->ajax()) {
            return false;
        }

        /** @var list<string> $except */
        $except = config()->array('made-cms-package.launcher.except', []);

        if ($request->routeIs('made-cms-package.*') || ($except !== [] && $request->is(...$except))) {
            return false;
        }

        if (! $response->isSuccessful()
            || $response instanceof JsonResponse
            || $response instanceof RedirectResponse
            || $response instanceof StreamedResponse
            || $response instanceof BinaryFileResponse
            || $response->headers->has('Content-Encoding')
            || str_contains(strtolower((string) $response->headers->get('Content-Disposition')), 'attachment')
            || ! str_contains(strtolower((string) $response->headers->get('Content-Type')), 'html')) {
            return false;
        }

        $content = $response->getContent();

        return is_string($content)
            && stripos($content, '</body>') !== false
            && ! str_contains($content, self::MARKER);
    }

    private function inject(Request $request, Response $response, LauncherView $view, ?Editor $editor): void
    {
        $content = (string) $response->getContent();
        $original = $response instanceof IlluminateResponse ? $response->original : null;

        $response->setContent(substr_replace(
            $content,
            $this->render($request, $view, $editor, $this->pageName($request, $content)),
            (int) strripos($content, '</body>'),
            0,
        ));

        // Host tests keep asserting on the view (assertViewIs, assertInertia) after the injection.
        if ($response instanceof IlluminateResponse && $original instanceof View) {
            $response->original = $original;
        }

        if ($response->headers->has('Content-Length')) {
            $response->headers->set('Content-Length', (string) strlen((string) $response->getContent()));
        }

        // The page now differs per visitor, so shared caches must never store it.
        $response->setPrivate();
        $response->headers->removeCacheControlDirective('s-maxage');
        $response->headers->addCacheControlDirective('no-store');
        $response->setEtag(null);
    }

    private function render(Request $request, LauncherView $view, ?Editor $editor, string $page): string
    {
        $locale = Locale::launcher();

        return view('made-cms-package-launcher::launcher', [
            'locale' => $locale,
            'view' => $view->value,
            'editor' => Visitor::profile($editor),
            'page' => $page,
            'endpoints' => Endpoints::all(),
            'site' => Site::name(),
            'stylesheet' => LauncherAssetController::url('launcher.css'),
            'script' => LauncherAssetController::url('launcher.js'),
            'fonts' => [
                'heading' => LauncherAssetController::url('nunito-700.woff2'),
                'text' => LauncherAssetController::url('instrument-sans.woff2'),
            ],
            'nonce' => class_exists(Vite::class) ? app(Vite::class)->cspNonce() : null,
        ])->render();
    }

    /**
     * The name of the page in the edit bar: what the host declares, else its title without the site name, else its path.
     */
    private function pageName(Request $request, string $content): string
    {
        $head = (string) strstr($content, '</head>', true) ?: $content;

        if (preg_match('/<meta\s[^>]*name=["\']made-cms:page["\'][^>]*>/i', $head, $meta) === 1
            && preg_match('/content=(["\'])(.*?)\1/is', $meta[0], $value) === 1
            && trim($value[2]) !== '') {
            return html_entity_decode(trim($value[2]), ENT_QUOTES | ENT_HTML5);
        }

        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $head, $title) === 1) {
            $parts = preg_split('/\s+[|\x{2013}\x{2014}\x{00B7}:-]\s+/u', html_entity_decode(trim(strip_tags($title[1])), ENT_QUOTES | ENT_HTML5)) ?: [];

            foreach ($parts as $part) {
                $part = trim($part);

                if ($part !== '' && strcasecmp($part, Site::name()) !== 0 && strcasecmp($part, Site::host()) !== 0) {
                    return $part;
                }
            }
        }

        $path = trim($request->path(), '/');

        if ($path === '') {
            return (string) __('made-cms-package::launcher.bar.home', [], Locale::launcher());
        }

        return Str::ucfirst(str_replace(['-', '_'], ' ', urldecode((string) Str::afterLast($path, '/'))));
    }
}
