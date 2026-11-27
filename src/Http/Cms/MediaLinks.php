<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Made\CmsPackage\Access\Enums\Screen;

/**
 * Addresses of the media library as another screen links to it: its address says what it lists and which file is
 * chosen (ShowMediaLibraryRequest). Only for an editor who opens Screen::Media; the caller decides that.
 */
final class MediaLinks
{
    /**
     * The library with this file chosen, its alt text in the side column.
     */
    public static function file(int $id): string
    {
        return self::library(['file' => $id]);
    }

    /**
     * Where the alt texts of photos are filled in: the one photo chosen, or the images of the library without one.
     */
    public static function missingAlt(int $count, int $first): string
    {
        return $count === 1 ? self::file($first) : self::library(['without_alt' => 1]);
    }

    /**
     * The library searched for a text: a name, an alt text or the name of a page a file is on.
     */
    public static function search(string $term): string
    {
        return self::library(['q' => $term]);
    }

    /**
     * @param  array<string, int|string>  $query
     */
    private static function library(array $query): string
    {
        return Menu::url(Screen::Media).'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
