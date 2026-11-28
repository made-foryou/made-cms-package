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
     * @param  bool|null  $inSitemap  whether the sitemap of the site lists the page; null keeps what the page has
     * @param  bool|null  $indexable  whether Google may show the page; null keeps what the page has
     * @param  int|null  $editorId  the editor who changes them; null when that is not known
     * @param  bool  $keepsTexts  whether the change leaves the texts as the page has them, so title and description say nothing
     */
    public function __construct(
        public ?string $title,
        public ?string $description,
        public ?bool $inSitemap = null,
        public ?bool $indexable = null,
        public ?int $editorId = null,
        public bool $keepsTexts = false,
    ) {}

    /**
     * Only whether the sitemap lists the page, as the screen of the sitemap changes it: the texts and whether Google may
     * show the page stay as they are.
     */
    public static function sitemap(bool $inSitemap, ?int $editorId = null): self
    {
        return new self(null, null, inSitemap: $inSitemap, editorId: $editorId, keepsTexts: true);
    }
}
