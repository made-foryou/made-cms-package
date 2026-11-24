<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\MenuPlaceReason;

/**
 * The place of a page with the menu switch on in the menu of the site (PageTree::menuPlaceOf()).
 */
final readonly class MenuPlace
{
    /**
     * @param  int|null  $position  from 1, among the pages next to it the menu of the site shows; for Position and Publish
     * @param  int|null  $total  the pages next to it the menu of the site shows, itself included; for Position and Publish
     * @param  int|null  $parentId  the page above it the menu of the site leaves out; for Parent
     */
    public function __construct(
        public MenuPlaceReason $reason,
        public ?int $position = null,
        public ?int $total = null,
        public ?int $parentId = null,
    ) {}
}
