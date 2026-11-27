<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PagesAttention;
use Made\CmsPackage\Content\Models\Page;

final class ListAttentionByPage
{
    use AsAction;

    /**
     * What every page asks for attention in a list of pages (the pages screen, later the start page and the menu of
     * the CMS): the points of ListPageAttention that let a page stand out there, so no list counts an empty page. The
     * pages are read once with their addresses, which the title Google gets needs, and the places of their files without
     * an alt text; a source reads only what is loaded, and what it knows of the other pages comes from these too.
     */
    public function handle(): PagesAttention
    {
        $all = Page::query()->with(['route', 'usagesWithoutAlt'])->get();
        $context = MakeAttentionContext::run($all);
        $pages = [];

        foreach ($all as $page) {
            $pages[$page->id] = ListPageAttention::run($page, $context)->inLists();
        }

        return new PagesAttention($pages);
    }
}
