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

    public static function restore(Page|int $page): string
    {
        return self::url('made-cms-package.pages.restore', ['page' => self::id($page)]);
    }

    /**
     * The edit shell: the page in the edit mode of the site, in a frame of the CMS.
     */
    public static function edit(Page|int $page): string
    {
        return self::url('made-cms-package.pages.edit', ['page' => self::id($page)]);
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
