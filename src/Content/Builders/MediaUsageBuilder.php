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
}
