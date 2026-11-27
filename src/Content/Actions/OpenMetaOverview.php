<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MetaOverview;
use Made\CmsPackage\Content\Data\MetaPanel;
use Made\CmsPackage\Content\Data\MetaRoundPlace;
use Made\CmsPackage\Content\Data\MetaRow;
use Made\CmsPackage\Content\Data\MetaRows;
use Made\CmsPackage\Content\Data\MetaSearch;
use Made\CmsPackage\Content\Enums\MetaFilter;
use Made\CmsPackage\Content\Models\Page;

final class OpenMetaOverview
{
    use AsAction;

    /**
     * The Meta-overzicht as asked, never refused: a filter that counts nothing gives way to the first that does, a
     * page that is not there to the first point, and the points done this round count at most every page. A point
     * opens at its place in the round ("k van n": those done, those passed by and this one, of those done and those
     * left); any other page opens on its own.
     */
    public function handle(MetaSearch $search): MetaOverview
    {
        $rows = ListMetaRows::run();
        $done = min(max(0, $search->done), count($rows->rows));
        $row = ($search->pageId === null ? null : $rows->find($search->pageId)) ?? ($rows->round()[0] ?? null);

        return new MetaOverview(
            rows: $rows,
            filter: $this->filter($rows, $search->filter),
            panel: $row === null ? null : $this->panel($rows, $row, $done),
            done: $done,
        );
    }

    private function filter(MetaRows $rows, ?MetaFilter $asked): MetaFilter
    {
        if ($asked !== null && ($asked->alwaysOffered() || $rows->count($asked) > 0)) {
            return $asked;
        }

        foreach ([MetaFilter::Missing, MetaFilter::Long, MetaFilter::Ok] as $filter) {
            if ($rows->count($filter) > 0) {
                return $filter;
            }
        }

        return MetaFilter::All;
    }

    private function panel(MetaRows $rows, MetaRow $row, int $done): ?MetaPanel
    {
        $page = Page::query()->with('route')->find($row->id);

        if (! $page instanceof Page) {
            return null;
        }

        $description = $row->measure->description;

        return new MetaPanel(
            row: $row,
            ownTitle: $page->meta_title,
            fallbackTitle: MakeFallbackTitle::run($page),
            meta: DescribePageMeta::run($page),
            suggestion: $description->missing || $description->tooLong ? SuggestPageDescription::run($page) : null,
            place: $this->place($rows, $row, $done),
        );
    }

    private function place(MetaRows $rows, MetaRow $row, int $done): ?MetaRoundPlace
    {
        $round = array_map(static fn (MetaRow $point): int => $point->id, $rows->round());
        $index = array_search($row->id, $round, true);

        if ($index === false) {
            return null;
        }

        return new MetaRoundPlace(
            position: $done + $index + 1,
            total: $done + count($round),
            done: $done,
            next: $rows->nextInRound($row->id),
        );
    }
}
