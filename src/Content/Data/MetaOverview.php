<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\MetaFilter;

/**
 * The Meta-overzicht (OpenMetaOverview): every page with how it does in Google, the filter of the list, the page open
 * beside it and the points this round put in order.
 */
final readonly class MetaOverview
{
    public function __construct(
        public MetaRows $rows,
        public MetaFilter $filter,
        public ?MetaPanel $panel,
        public int $done,
    ) {}

    public function count(MetaFilter $filter): int
    {
        return $this->rows->count($filter);
    }

    /**
     * The pages Google shows without a description or with a text it cuts off.
     */
    public function points(): int
    {
        return count($this->rows->round());
    }

    /**
     * How many pages Google shows: those the filters missing, long and in order split.
     */
    public function shown(): int
    {
        return $this->count(MetaFilter::Missing) + $this->count(MetaFilter::Long) + $this->count(MetaFilter::Ok);
    }

    /**
     * Whether every page Google shows has a description and texts that fit.
     */
    public function finished(): bool
    {
        return $this->shown() > 0 && $this->points() === 0;
    }

    /**
     * The filters the screen offers: in order and all always, missing and long while they count a page.
     *
     * @return list<MetaFilter>
     */
    public function offered(): array
    {
        return array_values(array_filter(
            MetaFilter::cases(),
            fn (MetaFilter $filter): bool => $filter->alwaysOffered() || $this->count($filter) > 0,
        ));
    }

    /**
     * The pages the list shows.
     *
     * @return list<MetaRow>
     */
    public function listed(): array
    {
        return $this->rows->inFilter($this->filter);
    }
}
