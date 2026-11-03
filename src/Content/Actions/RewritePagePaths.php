<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Models\Page;

final class RewritePagePaths
{
    use AsAction;

    /**
     * Gives a page a new address and the pages under it the addresses that follow from it.
     *
     * The caller checks that the new address is free and runs this in its transaction. The tree is consistent
     * (every address is that of its parent plus its slug), so no address in between clashes with another page.
     */
    public function handle(Page $page, PagePath $path): void
    {
        $page->route()->updateOrCreate([], ['path' => $path->value]);
        $page->unsetRelation('route');

        foreach (Page::query()->childrenOf($page)->inMenuOrder()->get() as $child) {
            $this->handle($child, $path->child($child->slug));
        }
    }
}
