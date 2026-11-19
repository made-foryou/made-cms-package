<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * A written sitemap: its xml and how many of the addresses it was given fit in it, the first ones in their order.
 */
final readonly class SitemapDocument
{
    public function __construct(
        public string $xml,
        public int $count,
    ) {}
}
