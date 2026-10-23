<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Launcher;

use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;
use Made\CmsPackage\Support\Locale;
use Made\CmsPackage\Support\Site;

/**
 * The page behind the link in the login mail, in the style of the panel. Its url holds a secret,
 * so it is never cached, framed or passed on as a referrer.
 */
final class LinkPage
{
    /**
     * @param  'confirm'|'other_browser'|'expired'  $state
     */
    public static function make(Request $request, string $state, string $token, int $status = 200): Response
    {
        $html = view('made-cms-package-launcher::link', [
            'locale' => Locale::launcher(),
            'state' => $state,
            'site' => Site::name(),
            'minutes' => config()->integer('made-cms-package.login_code.ttl_minutes'),
            'action' => $request->getBaseUrl().route('made-cms-package.login.link.confirm', ['token' => $token], false),
            'stylesheet' => LauncherAssetController::url('launcher.css'),
            'script' => LauncherAssetController::url('launcher.js'),
            'fonts' => [
                'heading' => LauncherAssetController::url('nunito-700.woff2'),
                'text' => LauncherAssetController::url('instrument-sans.woff2'),
            ],
            'nonce' => class_exists(Vite::class) ? app(Vite::class)->cspNonce() : null,
        ])->render();

        return new Response($html, $status, [
            'Cache-Control' => 'no-store, private',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
