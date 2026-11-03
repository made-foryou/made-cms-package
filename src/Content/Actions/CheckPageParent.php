<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Exceptions\CircularPageMove;
use Made\CmsPackage\Content\Exceptions\HomePageProtected;
use Made\CmsPackage\Content\Models\Page;

final class CheckPageParent
{
    use AsAction;

    /**
     * Refuses a parent a page cannot lie under: the page itself, a page under it, or any parent for the home page.
     *
     * @throws HomePageProtected
     * @throws CircularPageMove
     */
    public function handle(Page $page, ?Page $parent): void
    {
        if ($parent === null) {
            return;
        }

        if ($page->route?->path === PagePath::HOME) {
            throw HomePageProtected::move($page);
        }

        for ($ancestor = $parent; $ancestor !== null; $ancestor = $ancestor->parent) {
            if ($ancestor->is($page)) {
                throw CircularPageMove::for($page, $parent);
            }
        }
    }
}
