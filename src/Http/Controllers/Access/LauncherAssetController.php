<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Access;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves the stylesheets, scripts and fonts of the launcher and the CMS pages straight from the package,
 * so hosts never publish anything.
 */
final class LauncherAssetController
{
    /**
     * The only files this controller serves: public name => [path below resources/launcher, content type].
     *
     * @var array<string, array{string, string}>
     */
    private const array ASSETS = [
        'launcher.css' => ['launcher.css', 'text/css; charset=utf-8'],
        'launcher.js' => ['launcher.js', 'text/javascript; charset=utf-8'],
        'cms.css' => ['cms.css', 'text/css; charset=utf-8'],
        'cms.js' => ['cms.js', 'text/javascript; charset=utf-8'],
        'edit.js' => ['edit.js', 'text/javascript; charset=utf-8'],
        'nunito-700.woff2' => ['fonts/nunito-700.woff2', 'font/woff2'],
        'instrument-sans.woff2' => ['fonts/instrument-sans.woff2', 'font/woff2'],
    ];

    /**
     * @var array<string, string>
     */
    private static array $versions = [];

    public function __invoke(string $asset): BinaryFileResponse
    {
        if (! array_key_exists($asset, self::ASSETS)) {
            throw new NotFoundHttpException;
        }

        [$file, $contentType] = self::ASSETS[$asset];

        return new BinaryFileResponse(self::path($file), 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /**
     * The url of an asset without scheme and host, versioned by its contents so it can be cached forever.
     * Keeps the base path of apps served from a subdirectory.
     */
    public static function url(string $asset): string
    {
        return request()->getBaseUrl().route('made-cms-package.launcher.asset', [
            'asset' => $asset,
            'v' => self::$versions[$asset] ??= substr((string) hash_file('xxh128', self::path(self::ASSETS[$asset][0])), 0, 12),
        ], false);
    }

    private static function path(string $file): string
    {
        return dirname(__DIR__, 4).'/resources/launcher/'.$file;
    }
}
