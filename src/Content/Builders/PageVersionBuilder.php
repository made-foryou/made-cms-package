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
    public function ofPage(Page|int $page): self
    {
        return $this->where('page_id', $page instanceof Page ? $page->id : $page);
    }

    /**
     * The versions before this number, which a newer publication never joins.
     */
    public function before(int $number): self
    {
        return $this->where('number', '<', $number);
    }

    /**
     * The pages that keep more versions than this, by id, deleted pages too.
     *
     * @return list<int>
     */
    public function pagesWithMoreThan(int $count): array
    {
        $ids = $this->select('page_id')->groupBy('page_id')->havingRaw('count(*) > ?', [$count])->orderBy('page_id')->pluck('page_id');

        return array_values(array_map(intval(...), $ids->all()));
    }

    public function newestFirst(): self
    {
        return $this->orderByDesc('number');
    }

    /**
     * The number of the newest version these rows hold; 0 without one.
     */
    public function highestNumber(): int
    {
        $number = $this->max('number');

        return is_numeric($number) ? (int) $number : 0;
    }
}
