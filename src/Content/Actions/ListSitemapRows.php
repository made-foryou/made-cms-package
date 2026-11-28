<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\SitemapRow;
use Made\CmsPackage\Content\Data\SitemapRows;
use Made\CmsPackage\Content\Data\SitePrefixes;
use Made\CmsPackage\Content\Enums\NotShownInGoogle;
use Made\CmsPackage\Content\Enums\SitemapStanding;
use Made\CmsPackage\Content\Models\Page;

final class ListSitemapRows
{
    use AsAction;

    /**
     * Every page in menu order (ListPages, the pages outside the menu included) with its place in the sitemap of the
     * route group with this prefix (SitemapStanding), the pages read once more with only what that place needs.
     */
    public function handle(string $prefix): SitemapRows
    {
        $tree = ListPages::run();
        $pages = Page::query()->forSitemap()->get()->keyBy('id');
        $rules = ListGoogleRobotsRules::run();
        $rows = [];

        foreach ($tree->flatten() as $node) {
            $page = $pages->get($node->id);

            if (! $page instanceof Page) {
                continue;
            }

            $standing = SitemapStanding::of(
                NotShownInGoogle::of($page->status, $page->hidden, $page->indexable),
                $page->in_sitemap,
                $node->path === null ? null : SitePrefixes::path($prefix, $node->path->value),
                $rules,
            );

            $rows[] = new SitemapRow($node->id, $node->title, $node->path, $node->depth, $page->published_at, $standing);
        }

        return new SitemapRows($tree, $rows);
    }
}
