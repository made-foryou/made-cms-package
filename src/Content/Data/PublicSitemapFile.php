<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Carbon\CarbonImmutable;

/**
 * A sitemap.xml in the public folder of the site, which the web server serves before the one of the CMS: when it last
 * changed, how many addresses it lists and how many pages of the sitemap of the CMS it misses (both null when it is
 * no xml).
 */
final readonly class PublicSitemapFile
{
    public function __construct(
        public CarbonImmutable $modifiedAt,
        public ?int $addressCount,
        public ?int $missingPages,
    ) {}
}
