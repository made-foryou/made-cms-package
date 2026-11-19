<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Carbon\CarbonImmutable;

/**
 * One address in the sitemap: its absolute url and when what it shows last changed, if that is known.
 */
final readonly class SitemapEntry
{
    public function __construct(
        public string $url,
        public ?CarbonImmutable $lastModified,
    ) {}
}
