<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Launcher;

use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Made\CmsPackage\Access\Enums\LoginPageStep;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;
use Made\CmsPackage\Support\Locale;
use Made\CmsPackage\Support\Site;

/**
 * The login page: the steps of the panel without the site around it, ending at "verder waar je was".
 * It differs per browser, so it is never cached or framed.
 */
final class LoginPage
{
    public static function make(Request $request, LoginPageStep $step, ?Editor $editor): Response
    {
        $home = $request->getBaseUrl().'/';

        $html = view('made-cms-package-launcher::login', [
            'locale' => Locale::launcher(),
            'mode' => 'page',
            'start' => $step->value,
            'editor' => Visitor::profile($editor),
            'endpoints' => Endpoints::all(),
            'site' => Site::name(),
            'home' => $home,
            'recent' => self::recent($home),
            'helpUrl' => config('made-cms-package.mail.help_url'),
            'stylesheet' => LauncherAssetController::url('launcher.css'),
            'script' => LauncherAssetController::url('launcher.js'),
            'fonts' => [
                'heading' => LauncherAssetController::url('nunito-700.woff2'),
                'text' => LauncherAssetController::url('instrument-sans.woff2'),
            ],
            'nonce' => class_exists(Vite::class) ? app(Vite::class)->cspNonce() : null,
        ])->render();

        return new Response($html, 200, [
            'Cache-Control' => 'no-store, private',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'same-origin',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * What the editor had open last. Static for now, as in the design (3.6); later this comes from the Content context.
     *
     * @return list<array{title: string, when: string, status: 'draft'|'live', url: string}>
     */
    private static function recent(string $home): array
    {
        return [
            ['title' => 'Home', 'when' => 'vandaag 11:20 · nog niet gepubliceerd', 'status' => 'draft', 'url' => $home],
            ['title' => 'Trainingstijden', 'when' => 'gisteren', 'status' => 'live', 'url' => $home],
            ['title' => 'Onze teams', 'when' => 'vorige week', 'status' => 'live', 'url' => $home],
        ];
    }
}
