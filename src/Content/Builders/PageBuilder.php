<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Builders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;

/**
 * @extends Builder<Page>
 */
final class PageBuilder extends Builder
{
    public function published(): self
    {
        return $this->where('status', PageStatus::Published);
    }

    public function draft(): self
    {
        return $this->where('status', PageStatus::Draft);
    }

    /**
     * The page whose address is the given path.
     */
    public function atPath(PagePath $path): self
    {
        return $this->whereRelation('route', 'path', '=', $path->value);
    }

    /**
     * Most recently edited pages first, for "continue where you were".
     */
    public function recentlyEdited(): self
    {
        return $this->orderByDesc('updated_at');
    }

    /**
     * The pages at the top of the site, without a parent; the home page is one of them.
     */
    public function roots(): self
    {
        return $this->whereNull('parent_id');
    }

    /**
     * The pages directly under a page; the root pages for none.
     */
    public function childrenOf(?Page $parent): self
    {
        return $parent === null ? $this->roots() : $this->where('parent_id', $parent->id);
    }

    /**
     * The order of the menu among pages with the same parent.
     */
    public function inMenuOrder(): self
    {
        return $this->orderBy('position')->orderBy('id');
    }

    /**
     * Deleted pages whose deletion is not later than the moment, oldest first: what pruning removes.
     */
    public function trashedBefore(CarbonImmutable $moment): self
    {
        return $this->onlyTrashed()->where('deleted_at', '<=', $moment)->orderBy('deleted_at');
    }

    /**
     * Most recently deleted pages first.
     */
    public function recentlyDeleted(): self
    {
        return $this->orderByDesc('deleted_at');
    }
}
