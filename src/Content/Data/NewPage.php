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
     */
    public function __construct(
        public string $title,
        public ?string $slug,
        public ?int $parentId,
        public bool $inMenu = true,
    ) {}
}
