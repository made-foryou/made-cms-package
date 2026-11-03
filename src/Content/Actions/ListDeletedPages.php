<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\DeletedPage;
use Made\CmsPackage\Content\Data\DeletedPages;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Models\Page;

final class ListDeletedPages
{
    use AsAction;

    /**
     * The deleted pages that can still be restored, most recently deleted first, with the address each gets back.
     */
    public function handle(): DeletedPages
    {
        $pages = Page::query()->withTrashed()->with('route')->recentlyDeleted()->get()->keyBy('id');

        return new DeletedPages(array_values($pages
            ->filter(static fn (Page $page): bool => $page->trashed())
            ->map(fn (Page $page): DeletedPage => new DeletedPage(
                id: $page->id,
                title: $page->title,
                slug: $page->slug,
                formerPath: $this->formerPath($page, $pages),
                status: $page->status,
                deletedAt: CarbonImmutable::instance($page->deleted_at ?? now()),
                parentId: $page->parent_id,
                parentDeleted: $page->parent_id !== null && ($pages->get($page->parent_id)?->trashed() ?? false),
            ))
            ->all()));
    }

    /**
     * The address of the nearest page above that is not deleted, followed by the slugs from there down.
     *
     * @param  Collection<int, Page>  $pages  every page, deleted or not, by id
     */
    private function formerPath(Page $page, Collection $pages): PagePath
    {
        $slugs = [$page->slug];
        $ancestor = $page->parent_id === null ? null : $pages->get($page->parent_id);

        while ($ancestor !== null && $ancestor->trashed()) {
            array_unshift($slugs, $ancestor->slug);
            $ancestor = $ancestor->parent_id === null ? null : $pages->get($ancestor->parent_id);
        }

        return PagePath::fromString((string) $ancestor?->route?->path.'/'.implode('/', $slugs));
    }
}
