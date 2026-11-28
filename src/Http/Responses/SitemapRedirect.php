<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Made\CmsPackage\Content\Data\BuiltSitemap;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Cms\SitemapLinks;

/**
 * Where the screen of the sitemap goes after a change: back at the page whose switch was saved, or back at the file
 * after it was made again. That part says once what happened, where the editor goes on, never the notice at the top of
 * the shell, which the jump leaves out of sight.
 */
final class SitemapRedirect
{
    /** The flash with the id of the page whose switch was just saved. */
    private const string SAVED = 'made-cms-package.sitemap_saved';

    /** The flash with what making the sitemap again gave. */
    private const string BUILT = 'made-cms-package.sitemap_built';

    public static function saved(Page $page, string $group): RedirectResponse
    {
        return redirect()->to(SitemapLinks::screen($group, SitemapLinks::row($page->id)))->with(self::SAVED, $page->id);
    }

    /**
     * @param  list<BuiltSitemap>  $built  every sitemap made again, one per route group
     */
    public static function built(array $built, string $group): RedirectResponse
    {
        $shown = array_find($built, static fn (BuiltSitemap $sitemap): bool => $sitemap->prefix === $group) ?? $built[0] ?? null;

        return redirect()->to(SitemapLinks::screen($group, SitemapLinks::FILE))->with(self::BUILT, $shown === null ? null : [
            'prefix' => $shown->prefix,
            'addresses' => $shown->addresses,
            'errors' => $shown->errors,
            'changed' => $shown->changed,
        ]);
    }

    /**
     * The id of the page whose switch was just saved, once.
     */
    public static function justSaved(Request $request): ?int
    {
        $page = $request->hasSession() ? $request->session()->get(self::SAVED) : null;

        return is_int($page) ? $page : null;
    }

    /**
     * What making the sitemap again just gave, once.
     */
    public static function justBuilt(Request $request): ?BuiltSitemap
    {
        $built = $request->hasSession() ? $request->session()->get(self::BUILT) : null;

        if (! is_array($built)) {
            return null;
        }

        return new BuiltSitemap(
            is_string($built['prefix'] ?? null) ? $built['prefix'] : '',
            is_int($built['addresses'] ?? null) ? $built['addresses'] : 0,
            is_int($built['errors'] ?? null) ? $built['errors'] : 0,
            ($built['changed'] ?? false) === true,
        );
    }
}
