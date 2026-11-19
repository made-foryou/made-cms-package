<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\SitemapCause;

/**
 * What led to building the sitemap: the cause, with the page whose change it was or the editor who built it by hand.
 */
final readonly class SitemapReason
{
    public function __construct(
        public SitemapCause $cause,
        public ?int $pageId = null,
        public ?int $editorId = null,
    ) {}
}
