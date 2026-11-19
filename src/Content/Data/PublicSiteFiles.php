<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The files in the public folder of the site that go before the sitemap and robots.txt of the CMS; null for a file
 * that is not there.
 */
final readonly class PublicSiteFiles
{
    public function __construct(
        public ?PublicSitemapFile $sitemap,
        public ?PublicRobotsFile $robots,
    ) {}
}
