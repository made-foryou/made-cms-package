<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * What an editor changes about how Google sees a page.
 */
final readonly class PageMetaChanges
{
    /**
     * @param  string|null  $title  the whole title in Google; null or blank falls back to the name of the page
     * @param  string|null  $description  the description in Google; null or blank leaves it to Google
     * @param  bool  $inSitemap  whether the sitemap of the site lists the page
     * @param  bool  $indexable  whether Google may show the page
     */
    public function __construct(
        public ?string $title,
        public ?string $description,
        public bool $inSitemap,
        public bool $indexable,
    ) {}
}
