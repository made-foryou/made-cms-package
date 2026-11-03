<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageTree;
use Made\CmsPackage\Content\Models\Page;

final class ListParentOptions
{
    use AsAction;

    /**
     * The pages a page may lie under: every page but itself and the pages under it. Every page for a new one.
     */
    public function handle(?Page $page = null): PageTree
    {
        $tree = ListPages::run();

        return $page === null ? $tree : $tree->without($page->id);
    }
}
