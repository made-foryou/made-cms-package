<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\EditableStrip;
use Made\CmsPackage\Content\Data\PageSummary;
use Made\CmsPackage\Content\Data\PageTree;
use Made\CmsPackage\Content\Enums\PageState;
use Made\CmsPackage\Content\Models\Page;

final class SummarizePage
{
    use AsAction;

    /**
     * A page in short, for the column "In het kort" and the card "Wat erop staat" of its screen.
     *
     * The tree is the one the screen read already (ListPages), so where the page lies and its place in the menu of the
     * site cost no query of their own; the strips are those of the working copy, as the edit shell lists them, and so are
     * its files (ListPageMedia).
     *
     * @param  PageTree  $pages  every page of the site
     */
    public function handle(Page $page, PageTree $pages): PageSummary
    {
        $strips = ListEditableStrips::run($page);
        $parent = $page->parent_id === null ? null : $pages->find($page->parent_id);

        return new PageSummary(
            state: PageState::of($page->status, $page->hidden),
            type: $page->type,
            parentId: $page->parent_id,
            parentTitle: $parent?->title,
            menuPlace: $pages->menuPlaceOf($page->id),
            children: count($pages->find($page->id)->children ?? []),
            strips: $strips,
            fields: array_sum(array_map(static fn (EditableStrip $strip): int => $strip->fields, $strips->strips)),
            createdAt: $page->created_at?->toImmutable(),
            createdBy: $page->created_by,
            publishedAt: $page->published_at,
            updatedAt: $page->updated_at?->toImmutable(),
            updatedBy: $page->updated_by,
            media: ListPageMedia::run($page),
        );
    }
}
