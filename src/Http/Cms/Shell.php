<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Illuminate\Foundation\Vite;
use Illuminate\Http\Response;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;
use Made\CmsPackage\Http\Launcher\Endpoints;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Support\Locale;
use Made\CmsPackage\Support\Site;

/**
 * The frame around every page of the CMS: the header with the editor, the menu of their screens and the assets.
 * The pages differ per editor, so they are never cached, framed or indexed.
 */
final class Shell
{
    /**
     * @param  view-string  $view
     * @param  list<Screen>  $screens  the screens of the editor, in menu order (ListScreens)
     * @param  array<string, mixed>  $data  what the page itself shows
     */
    public static function respond(string $view, Editor $editor, array $screens, ?Screen $current, array $data): Response
    {
        $html = view($view, [
            ...self::data($editor, $screens, $current),
            ...$data,
        ])->render();

        return new Response($html, 200, [
            'Cache-Control' => 'no-store, private',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'same-origin',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * Where an editor gets help from MADE, when the host configured it.
     */
    public static function helpUrl(): ?string
    {
        $url = config('made-cms-package.mail.help_url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * @param  list<Screen>  $screens
     * @return array<string, mixed>
     */
    private static function data(Editor $editor, array $screens, ?Screen $current): array
    {
        $locale = Locale::launcher();

        return [
            'locale' => $locale,
            't' => fn (string $key, array $replace = []): string => (string) __('made-cms-package::'.$key, $replace, $locale),
            'site' => Site::name(),
            'home' => request()->getBaseUrl().'/',
            'startUrl' => Endpoints::url('made-cms-package.start'),
            'editor' => Visitor::profile($editor),
            'roles' => implode(' · ', array_map(
                fn (Role $role): string => $role->label(),
                Role::ordered($editor->roles->all()),
            )),
            'menu' => Menu::groups($screens, $current),
            'current' => $current,
            'helpUrl' => self::helpUrl(),
            'stylesheet' => LauncherAssetController::url('cms.css'),
            'script' => LauncherAssetController::url('cms.js'),
            'fonts' => [
                'heading' => LauncherAssetController::url('nunito-700.woff2'),
                'text' => LauncherAssetController::url('instrument-sans.woff2'),
            ],
            'nonce' => class_exists(Vite::class) ? app(Vite::class)->cspNonce() : null,
        ];
    }
}
