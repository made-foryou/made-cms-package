<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Builders;

use Illuminate\Database\Eloquent\Builder;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\PageVersion;

/**
 * @extends Builder<PageVersion>
 */
final class PageVersionBuilder extends Builder
{
    public function ofPage(Page $page): self
    {
        return $this->where('page_id', $page->id);
    }

    public function newestFirst(): self
    {
        return $this->orderByDesc('number');
    }
}
