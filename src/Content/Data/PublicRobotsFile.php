<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * A robots.txt in the public folder of the site, which the web server serves before the one of the CMS: whether a
 * Sitemap line of it names the sitemap of the CMS.
 */
final readonly class PublicRobotsFile
{
    public function __construct(
        public bool $namesCmsSitemap,
    ) {}
}
