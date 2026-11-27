<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Attention;

use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Models\Page;

/**
 * Something that knows what a page asks for: a missing description, a draft that waits too long, a photo without an
 * alt text, later a broken link. A source only reads; ListPageAttention puts what the sources give in order.
 */
interface AttentionSource
{
    /**
     * @return list<AttentionItem>
     */
    public function items(Page $page): array;
}
