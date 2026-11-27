<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Made\CmsPackage\Content\Models\Page;

/**
 * The urls of the pages screen, without scheme and host and with the base path of apps in a subdirectory.
 */
final class PageLinks
{
    public static function index(): string
    {
        return self::url('made-cms-package.screens.pages');
    }

    public static function create(?int $parentId = null): string
    {
        return self::url('made-cms-package.pages.create', $parentId === null ? [] : ['parent' => $parentId]);
    }

    public static function store(): string
    {
        return self::url('made-cms-package.pages.store');
    }

    public static function show(Page|int $page): string
    {
        return self::url('made-cms-package.pages.show', ['page' => self::id($page)]);
    }

    public static function update(Page $page): string
    {
        return self::url('made-cms-package.pages.update', ['page' => $page->id]);
    }

    public static function meta(Page $page): string
    {
        return self::url('made-cms-package.pages.meta', ['page' => $page->id]);
    }

    /**
     * Where the card "Hoe je link gedeeld wordt" saves the image of a shared link to the page.
     */
    public static function shareImage(Page $page): string
    {
        return self::url('made-cms-package.pages.share-image', ['page' => $page->id]);
    }

    /**
     * Where the tree of the pages screen moves a page.
     */
    public static function move(Page|int $page): string
    {
        return self::url('made-cms-package.pages.position', ['page' => self::id($page)]);
    }

    /**
     * Where the card Weghalen hides a page from visitors or shows it again.
     */
    public static function visibility(Page $page): string
    {
        return self::url('made-cms-package.pages.visibility', ['page' => $page->id]);
    }

    public static function destroy(Page $page): string
    {
        return self::url('made-cms-package.pages.destroy', ['page' => $page->id]);
    }

    public static function duplicate(Page $page): string
    {
        return self::url('made-cms-package.pages.duplicate', ['page' => $page->id]);
    }

    /**
     * Where "Toch nu publiceren" in the basics puts the draft of a page with a planned publication live at once.
     */
    public static function publishNow(Page $page): string
    {
        return self::url('made-cms-package.pages.publish.now', ['page' => $page->id]);
    }

    public static function restore(Page|int $page): string
    {
        return self::url('made-cms-package.pages.restore', ['page' => self::id($page)]);
    }

    /**
     * Where the card "Wat er eerder gebeurde" puts a version of the page back as its draft.
     */
    public static function restoreVersion(Page $page, int $number): string
    {
        return self::url('made-cms-package.pages.versions.restore', ['page' => $page->id, 'version' => $number]);
    }

    /**
     * The edit shell: the page in the edit mode of the site, in a frame of the CMS; with a strip, opened with that
     * strip chosen.
     */
    public static function edit(Page|int $page, ?string $strip = null): string
    {
        $url = self::url('made-cms-package.pages.edit', ['page' => self::id($page)]);

        return $strip === null ? $url : $url.'?'.http_build_query(['strip' => $strip]);
    }

    private static function id(Page|int $page): int
    {
        return $page instanceof Page ? $page->id : $page;
    }

    /**
     * @param  array<string, int>  $parameters
     */
    private static function url(string $route, array $parameters = []): string
    {
        return request()->getBaseUrl().route($route, $parameters, false);
    }
}
