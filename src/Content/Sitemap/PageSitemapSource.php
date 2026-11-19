<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Sitemap;

use Made\CmsPackage\Content\Data\SitemapCandidate;
use Made\CmsPackage\Content\Models\Page;

/**
 * The pages of the sitemap: published, seen by visitors, shown by Google and kept in the sitemap by their switch, each
 * by itself (a page under a hidden page is still for Google, as its meta says), in the order of their addresses.
 */
final readonly class PageSitemapSource implements SitemapSource
{
    public function candidates(): iterable
    {
        $pages = Page::query()
            ->published()
            ->visible()
            ->indexable()
            ->inSitemap()
            ->with('route')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Page $page): string => $page->route->path ?? '', SORT_STRING);

        foreach ($pages as $page) {
            yield new SitemapCandidate($page->title, $page->route?->path, $page->published_at);
        }
    }
}
