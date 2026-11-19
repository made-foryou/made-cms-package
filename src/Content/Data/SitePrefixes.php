<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The prefixes of the route groups that serve the pages of the site with `Route::madeCms()`, without slashes: "" for
 * a group at the root, "nl" for one under /nl. Usually one. The service provider reads them from the routes.
 */
final readonly class SitePrefixes
{
    /**
     * @param  list<string>  $prefixes
     */
    public function __construct(
        public array $prefixes,
    ) {}

    /**
     * The path on the site of a path under a route group: "/nl/over-ons", or "/nl" for the home page of that group.
     */
    public static function path(string $prefix, string $path): string
    {
        return '/'.trim(trim($prefix, '/').'/'.trim($path, '/'), '/');
    }
}
