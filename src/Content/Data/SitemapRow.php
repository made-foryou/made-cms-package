<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Enums\SitemapStanding;

/**
 * A page on the screen of the sitemap: its name, address and depth in the menu, when it was last published (its
 * lastmod) and whether it is in the sitemap.
 */
final readonly class SitemapRow
{
    public function __construct(
        public int $id,
        public string $name,
        public ?PagePath $path,
        public int $depth,
        public ?CarbonImmutable $publishedAt,
        public SitemapStanding $standing,
    ) {}
}
