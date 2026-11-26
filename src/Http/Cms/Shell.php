<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Illuminate\Foundation\Vite;
use Illuminate\Http\Response;
use Illuminate\Support\ViewErrorBag;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\ListPages;
use Made\CmsPackage\Content\Data\PageTree;
use Made\CmsPackage\Content\Models\Page;
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
    /** The flash with the notice after a change, shown on top of the next page. */
    public const string NOTICE = 'made-cms-package.notice';

    /**
     * @param  view-string  $view
     * @param  list<Screen>  $screens  the screens of the editor, in menu order (ListScreens)
     * @param  array<string, mixed>  $data  what the page itself shows
     * @param  PageTree|null  $pages  the pages of the site for the menu, when the page has them already
     * @param  Page|null  $page  the page whose screen is open
     */
    public static function respond(string $view, Editor $editor, array $screens, ?Screen $current, array $data, ?PageTree $pages = null, ?Page $page = null): Response
    {
        $html = view($view, [
            ...self::data($editor, $screens, $current, $pages, $page),
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
    private static function data(Editor $editor, array $screens, ?Screen $current, ?PageTree $pages, ?Page $page): array
    {
        $locale = Locale::launcher();
        $pages ??= in_array(Screen::Pages, $screens, true) ? ListPages::run() : null;

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
            'menu' => Menu::groups($screens, $current, $pages, $page),
            'notice' => self::notice(),
            // The form errors of the last request; also when the host's web group does not share them.
            'errors' => self::errors(),
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

    /**
     * The notice a change left in the session: its text and, after deleting a page, where to restore it.
     *
     * @return array{text: string, restoreUrl: string|null}|null
     */
    private static function notice(): ?array
    {
        $notice = request()->hasSession() ? request()->session()->get(self::NOTICE) : null;

        if (! is_array($notice) || ! is_string($notice['text'] ?? null) || ! is_string($notice['title'] ?? null)) {
            return null;
        }

        $restore = $notice['restore'] ?? null;
        $origin = $notice['origin'] ?? null;

        return [
            'text' => (string) __('made-cms-package::pages.notices.'.$notice['text'], ['title' => $notice['title'], 'origin' => is_string($origin) ? $origin : '']),
            'restoreUrl' => is_int($restore) ? PageLinks::restore($restore) : null,
        ];
    }

    private static function errors(): ViewErrorBag
    {
        $errors = request()->hasSession() ? request()->session()->get('errors') : null;

        return $errors instanceof ViewErrorBag ? $errors : new ViewErrorBag;
    }
}
