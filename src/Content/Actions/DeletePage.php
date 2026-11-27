<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Events\PageDeleted;
use Made\CmsPackage\Content\Exceptions\HomePageProtected;
use Made\CmsPackage\Content\Exceptions\PageHasChildren;
use Made\CmsPackage\Content\Models\Page;

final class DeletePage
{
    use AsAction;

    /**
     * Deletes a page: visitors and editors no longer find it and its address is free again. It keeps its content
     * and can be restored until it is pruned. Its planned publication goes, so it never goes live once restored.
     *
     * @throws HomePageProtected
     * @throws PageHasChildren
     */
    public function handle(Page $page): void
    {
        $page->loadMissing('route');
        $path = PagePath::fromString((string) $page->route?->path);

        if ($page->route?->path === PagePath::HOME) {
            throw HomePageProtected::delete($page);
        }

        if (Page::query()->childrenOf($page)->exists()) {
            throw PageHasChildren::for($page);
        }

        DB::transaction(function () use ($page): void {
            if ($page->publish_at !== null) {
                $page->publish_at = null;
                $page->scheduled_by = null;
                $page->save();
            }

            $page->route()->delete();
            $page->unsetRelation('route');
            $page->delete();

            RenumberPages::run(array_values(Page::query()->childrenOf($page->parent)->inMenuOrder()->get()
                ->map(static fn (Page $sibling): int => $sibling->id)->all()));
        });

        PageDeleted::dispatch($page, $path);
    }
}
