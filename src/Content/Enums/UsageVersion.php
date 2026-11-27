<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

use Made\CmsPackage\Content\Models\Page;

/**
 * The version of a page a place is in: the draft editors work on (draft_strips) or what visitors see (strips). A place
 * of the page itself (a PagePlace, such as its share image) lies outside the draft and holds for both at once.
 */
enum UsageVersion: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Page = 'page';

    /**
     * The version whose places are on the working copy of a page: its draft while it has one, else what visitors see.
     */
    public static function workingCopyOf(Page $page): self
    {
        return $page->draft_strips === null ? self::Published : self::Draft;
    }
}
