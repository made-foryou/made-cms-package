<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Listeners;

use Made\CmsPackage\Content\Actions\BuildSitemaps;
use Made\CmsPackage\Content\Data\SitemapReason;
use Made\CmsPackage\Content\Enums\SitemapCause;
use Made\CmsPackage\Content\Events\PageDeleted;
use Made\CmsPackage\Content\Events\PageMetaUpdated;
use Made\CmsPackage\Content\Events\PageMoved;
use Made\CmsPackage\Content\Events\PagePublished;
use Made\CmsPackage\Content\Events\PageRestored;
use Made\CmsPackage\Content\Events\PageUpdated;
use Made\CmsPackage\Content\Events\PageVisibilityChanged;

use function Illuminate\Support\defer;

/**
 * Builds the sitemaps again after a change of a page that can change them. It waits until the answer is sent (or the
 * command or job is done), so publishing never waits for it, and builds once however many pages changed: the last
 * change names the reason.
 */
final class RebuildSitemaps
{
    public const string DEFERRED = 'made-cms:sitemap';

    public function handle(PagePublished|PageUpdated|PageMoved|PageDeleted|PageRestored|PageVisibilityChanged|PageMetaUpdated $event): void
    {
        $reason = new SitemapReason(self::cause($event), pageId: $event->page->id);

        defer(static function () use ($reason): void {
            BuildSitemaps::run($reason);
        }, self::DEFERRED);
    }

    private static function cause(PagePublished|PageUpdated|PageMoved|PageDeleted|PageRestored|PageVisibilityChanged|PageMetaUpdated $event): SitemapCause
    {
        return match (true) {
            $event instanceof PagePublished => SitemapCause::Published,
            $event instanceof PageUpdated => SitemapCause::Updated,
            $event instanceof PageMoved => SitemapCause::Moved,
            $event instanceof PageDeleted => SitemapCause::Deleted,
            $event instanceof PageRestored => SitemapCause::Restored,
            $event instanceof PageVisibilityChanged => SitemapCause::Visibility,
            $event instanceof PageMetaUpdated => SitemapCause::Meta,
        };
    }
}
