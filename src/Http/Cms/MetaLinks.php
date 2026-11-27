<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Content\Enums\MetaFilter;

/**
 * The addresses of the Meta-overzicht: the screen at a filter, a page and the points done this round, and saving a
 * page. A link that opens a page lands on the panel beside the list, also on a narrow screen where it lies below.
 */
final class MetaLinks
{
    public const string PANEL = 'made-cms-afwerken';

    public static function screen(?MetaFilter $filter = null, ?int $page = null, int $done = 0, bool $panel = false): string
    {
        $query = array_filter([
            'filter' => $filter?->value,
            'page' => $page,
            'done' => $done > 0 ? $done : null,
        ], static fn (string|int|null $value): bool => $value !== null);

        return Menu::url(Screen::MetaTags)
            .($query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986))
            .($panel ? '#'.self::PANEL : '');
    }

    public static function update(int $page): string
    {
        return request()->getBaseUrl().route('made-cms-package.meta-tags.update', ['page' => $page], false);
    }
}
