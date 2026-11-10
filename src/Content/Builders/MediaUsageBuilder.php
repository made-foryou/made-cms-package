<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Builders;

use Illuminate\Database\Eloquent\Builder;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;

/**
 * @extends Builder<MediaUsage>
 */
final class MediaUsageBuilder extends Builder
{
    /**
     * The places on a page, in both its versions, also when the page is deleted.
     */
    public function onPage(Page $page): self
    {
        return $this->where('page_id', $page->id);
    }

    /**
     * The number of places these rows are: a place in the draft and in the published version of a page counts once,
     * as the library counts it.
     */
    public function placeCount(): int
    {
        return $this->getQuery()->newQuery()
            ->fromSub($this->clone()->select(['page_id', 'strip_id', 'field'])->distinct(), 'places')
            ->count();
    }
}
