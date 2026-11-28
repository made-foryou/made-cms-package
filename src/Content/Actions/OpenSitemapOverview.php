<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Data\SitemapFetches;
use Made\CmsPackage\Content\Data\SitemapOverview;
use Made\CmsPackage\Content\Data\SitemapRow;
use Made\CmsPackage\Content\Data\SitemapRows;
use Made\CmsPackage\Content\Data\SitemapSearch;
use Made\CmsPackage\Content\Data\SitePrefixes;
use Made\CmsPackage\Content\Enums\HitOutcome;
use Made\CmsPackage\Content\Enums\VisitSource;
use Made\CmsPackage\Content\Models\AddressHit;
use Made\CmsPackage\Content\Models\Page;

final class OpenSitemapOverview
{
    use AsAction;

    public function __construct(
        private readonly SitePrefixes $prefixes,
    ) {}

    /**
     * The screen of the sitemap of the route group asked for, or of the first group (the root first) for a group that
     * serves no pages: never a refusal. Without a group that serves pages there is no sitemap, and none is built.
     */
    public function handle(SitemapSearch $search): SitemapOverview
    {
        $prefixes = $this->prefixes->prefixes;
        $prefix = in_array($search->prefix, $prefixes, true) ? (string) $search->prefix : ($prefixes[0] ?? '');
        $rows = ListSitemapRows::run($prefix);
        $sitemap = $prefixes === [] ? null : OpenSitemap::run($prefix);

        return new SitemapOverview(
            $prefixes,
            $prefix,
            $sitemap,
            $sitemap?->page_id === null ? null : self::title($sitemap->page_id),
            $rows,
            $prefixes === [] ? null : CheckPublicSiteFiles::run($prefix),
            self::fetches($prefix, $rows),
        );
    }

    /**
     * The name of the page whose change built the sitemap, also when it was deleted since.
     */
    private static function title(int $pageId): ?string
    {
        $title = Page::query()->withTrashed()->whereKey($pageId)->value('title');

        return is_string($title) ? $title : null;
    }

    private static function fetches(string $prefix, SitemapRows $rows): SitemapFetches
    {
        $lastFetch = AddressHit::query()
            ->at(PagePath::fromString('sitemap.xml'), $prefix)
            ->outcome(HitOutcome::Served)
            ->from(VisitSource::Googlebot)
            ->lastDay();

        $changed = $lastFetch === null ? [] : array_filter(
            $rows->in(),
            static fn (SitemapRow $row): bool => $row->publishedAt !== null && $row->publishedAt->gt($lastFetch->endOfDay()),
        );

        return new SitemapFetches(
            $lastFetch,
            AddressHit::query()->firstDay(),
            AddressHit::query()->inGroup($prefix)->outcome(HitOutcome::Page)->from(VisitSource::Googlebot)->totalHits(),
            count($changed),
        );
    }
}
