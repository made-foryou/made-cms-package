<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * What an editor changes about a page in the CMS: its name, its address, its place, whether visitors see it and its type.
 */
final readonly class PageChanges
{
    /**
     * @param  string|null  $slug  the last segment of the address; null or blank makes it from the title
     * @param  int|null  $parentId  the page it lies under; null for the top of the site
     * @param  bool|null  $hidden  whether visitors only reach it through its address; null keeps it as it is
     * @param  int|null  $editorId  the editor who changes it; null when that is not known
     * @param  PageTypeChoice|null  $type  the type it gets; null keeps it as it is
     */
    public function __construct(
        public string $title,
        public ?string $slug,
        public ?int $parentId,
        public bool $inMenu,
        public ?bool $hidden = null,
        public ?int $editorId = null,
        public ?PageTypeChoice $type = null,
    ) {}
}
