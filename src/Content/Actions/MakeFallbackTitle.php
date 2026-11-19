<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Support\Site;

final class MakeFallbackTitle
{
    use AsAction;

    /**
     * The title in Google of a page without a title of its own: its name and the name of the site, or the name of the
     * site alone for the home page. A title of its own is the whole title, without the name of the site after it.
     */
    public function handle(Page $page): string
    {
        if ($page->route?->path === PagePath::HOME) {
            return Site::name();
        }

        return $page->title.' · '.Site::name();
    }
}
