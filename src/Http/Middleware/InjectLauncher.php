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
    public const string COOKIE = 'made_cms_editor';

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
            remembered: $request->cookie(self::COOKIE) === '1',
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
        $locale = $this->locale();

        return view('made-cms-package-launcher::launcher', [
            'locale' => $locale,
            'site' => $this->siteName($this->host($request)),
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
     * The configured locale when the package ships it, English otherwise; never raw translation keys.
     */
    private function locale(): string
    {
        $locale = config()->string('made-cms-package.launcher.locale', 'nl');

        return app('translator')->hasForLocale('made-cms-package::launcher.title', $locale) ? $locale : 'en';
    }

    /**
     * The name in the panel header: the application name, unless the host never changed Laravel's default.
     */
    private function siteName(string $host): string
    {
        $name = config('app.name');

        return is_string($name) && trim($name) !== '' && $name !== 'Laravel' ? $name : $host;
    }

    /**
     * The site host, taken from the configured url rather than the request's Host header.
     */
    private function host(Request $request): string
    {
        $url = config('app.url');
        $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;

        if (! is_string($host) || $host === '') {
            $host = $request->getHost();
        }

        return (string) preg_replace('/^www\./i', '', $host);
    }
}
