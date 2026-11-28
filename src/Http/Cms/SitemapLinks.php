<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Made\CmsPackage\Access\Enums\Screen;

/**
 * The addresses of the screen of the sitemap: the screen of a route group (the group "" at the root needs no
 * parameter), with a part to land on, and the forms of a page and of the file.
 */
final class SitemapLinks
{
    /** The part with the file itself, where "Opnieuw genereren" comes back. */
    public const string FILE = 'made-cms-sitemap-bestand';

    public static function screen(string $prefix = '', ?string $anchor = null): string
    {
        return Menu::url(Screen::Sitemap)
            .($prefix === '' ? '' : '?'.http_build_query(['groep' => $prefix], '', '&', PHP_QUERY_RFC3986))
            .($anchor === null ? '' : '#'.$anchor);
    }

    /**
     * The part of a page in the list, where its switch comes back.
     */
    public static function row(int $page): string
    {
        return 'made-cms-sitemap-'.$page;
    }

    public static function update(int $page): string
    {
        return request()->getBaseUrl().route('made-cms-package.sitemap.update', ['page' => $page], false);
    }

    public static function build(): string
    {
        return request()->getBaseUrl().route('made-cms-package.sitemap.build', [], false);
    }
}
