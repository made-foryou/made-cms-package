<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Data\PageTree;
use Made\CmsPackage\Content\Models\Page;

final class ListParentOptions
{
    use AsAction;

    /**
     * The pages a page may lie under: every page but itself and the pages under it. Every page for a new one.
     *
     * The home page is no option, because under it a page gets the same address as at the top; the pages under the
     * home page take its place. A page that lies under the home page already keeps it as an option.
     */
    public function handle(?Page $page = null): PageTree
    {
        $tree = ListPages::run();

        if ($page !== null) {
            $tree = $tree->without($page->id);
        }

        foreach ($tree->roots as $root) {
            if ($root->path?->value === PagePath::HOME && $root->id !== $page?->parent_id) {
                return $tree->lift($root->id);
            }
        }

        return $tree;
    }
}
