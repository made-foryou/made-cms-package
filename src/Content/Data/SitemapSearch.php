<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * Which sitemap the screen of the sitemap opens: the prefix of its route group, null for the first group.
 */
final readonly class SitemapSearch
{
    public function __construct(
        public ?string $prefix,
    ) {}
}
