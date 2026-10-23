<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Middleware;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Made\CmsPackage\Access\Actions\ShowLauncher;
use Made\CmsPackage\Access\Data\LauncherVisitor;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;
use Made\CmsPackage\Http\Cookies\EditorDevice;
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
            if ($this->isFullPage($request, $response) && ShowLauncher::run($this->visitor($request))) {
                $this->inject($request, $response);
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

    private function visitor(Request $request): LauncherVisitor
    {
        return new LauncherVisitor(
            ip: $request->ip(),
            remembered: EditorDevice::remembered($request),
        );
    }

    private function inject(Request $request, Response $response): void
    {
        $content = (string) $response->getContent();
        $original = $response instanceof IlluminateResponse ? $response->original : null;

        $response->setContent(substr_replace(
            $content,
            $this->render($request),
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

    private function render(Request $request): string
    {
        return view('made-cms-package-launcher::launcher', [
            'locale' => Locale::launcher(),
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
}
