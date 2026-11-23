<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Data\PageTree;
use Made\CmsPackage\Content\Models\Page;

final class ListPages
{
    use AsAction;

    /**
     * Every page of the site as a tree in menu order, the pages outside the menu included.
     */
    public function handle(): PageTree
    {
        $byParent = [];

        foreach (Page::query()->with('route')->inMenuOrder()->get() as $page) {
            $byParent[$page->parent_id ?? 0][] = $page;
        }

        return new PageTree($this->nodes($byParent, 0, 0));
    }

    /**
     * @param  array<int, list<Page>>  $byParent
     * @return list<PageNode>
     */
    private function nodes(array $byParent, int $parentId, int $depth): array
    {
        return array_map(fn (Page $page): PageNode => new PageNode(
            id: $page->id,
            title: $page->title,
            slug: $page->slug,
            path: $page->route === null ? null : PagePath::fromString($page->route->path),
            status: $page->status,
            inMenu: $page->in_menu,
            hidden: $page->hidden,
            position: $page->position,
            parentId: $page->parent_id,
            depth: $depth,
            hasDraft: $page->draft_strips !== null,
            isEmpty: CheckPageEmpty::run($page),
            updatedAt: CarbonImmutable::instance($page->updated_at ?? now()),
            updatedBy: $page->updated_by,
            draftStartedAt: $page->draft_started_at,
            children: $this->nodes($byParent, $page->id, $depth + 1),
        ), $byParent[$parentId] ?? []);
    }
}
