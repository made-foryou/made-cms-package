<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Exceptions\CircularPageMove;
use Made\CmsPackage\Content\Exceptions\HomePageProtected;
use Made\CmsPackage\Content\Exceptions\ParentPageDeleted;
use Made\CmsPackage\Content\Exceptions\ParentPageNotFound;
use Made\CmsPackage\Content\Models\Page;

final class CheckPageParent
{
    use AsAction;

    /**
     * Refuses a parent a page cannot lie under: the page itself, a page under it, a page deleted by now, or any parent
     * for the home page.
     *
     * Run it in the transaction that moves the page. It reads the parent and every page above it again with their rows
     * locked, never the parent it is handed, so it sees the tree as it is now, and a move of one of them at the same
     * moment waits until this one is committed. It stops at a page it saw before, so a loop in the tree never runs for
     * ever.
     *
     * @throws HomePageProtected
     * @throws CircularPageMove
     * @throws ParentPageDeleted
     * @throws ParentPageNotFound
     */
    public function handle(Page $page, ?Page $parent): void
    {
        if ($parent === null) {
            return;
        }

        if ($page->route?->path === PagePath::HOME) {
            throw HomePageProtected::move($page);
        }

        $seen = [];

        for ($id = $parent->id; $id !== null && ! in_array($id, $seen, true); $id = $ancestor->parent_id) {
            if ($id === $page->id) {
                throw CircularPageMove::for($page, $parent);
            }

            $seen[] = $id;
            $ancestor = Page::query()->withTrashed()->whereKey($id)->lockForUpdate()->first() ?? throw ParentPageNotFound::id($id);

            if ($ancestor->trashed()) {
                throw ParentPageDeleted::for($ancestor);
            }
        }
    }
}
