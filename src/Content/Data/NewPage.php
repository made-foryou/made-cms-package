<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * What an editor tells about a page to create.
 */
final readonly class NewPage
{
    /**
     * @param  string|null  $slug  the last segment of the address; null or blank makes it from the title
     * @param  int|null  $parentId  the page it lies under; null for the top of the site
     * @param  int|null  $editorId  the editor who creates it; null when that is not known, such as for an import
     * @param  string|null  $type  the key of its page type; null for an ordinary page
     */
    public function __construct(
        public string $title,
        public ?string $slug,
        public ?int $parentId,
        public bool $inMenu = true,
        public ?int $editorId = null,
        public ?string $type = null,
    ) {}
}
