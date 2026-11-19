<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageTree;

final class ListMenuPages
{
    use AsAction;

    /**
     * The menu of the site: the published pages visitors see with the menu switch on, in menu order. A draft, a
     * hidden page or a page outside the menu takes the pages under it along; deleted pages are never in it.
     */
    public function handle(): PageTree
    {
        return ListPages::run()->inMenu()->published()->visible();
    }
}
