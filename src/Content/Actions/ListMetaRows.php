<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MetaRow;
use Made\CmsPackage\Content\Data\MetaRows;
use Made\CmsPackage\Content\Models\Page;

final class ListMetaRows
{
    use AsAction;

    /**
     * Every page in menu order (ListPages, the pages outside the menu included) with how it does in Google
     * (MeasurePageMeta), the pages read once with their addresses.
     */
    public function handle(): MetaRows
    {
        $tree = ListPages::run();
        $pages = Page::query()->with('route')->get()->keyBy('id');
        $rows = [];

        foreach ($tree->flatten() as $node) {
            $page = $pages->get($node->id);

            if ($page instanceof Page) {
                $rows[] = new MetaRow($node->id, $node->title, $node->path, MeasurePageMeta::run($page));
            }
        }

        return new MetaRows($tree, $rows);
    }
}
