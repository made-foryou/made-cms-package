<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageMetaChanges;
use Made\CmsPackage\Content\Data\SitemapReason;
use Made\CmsPackage\Content\Enums\SitemapCause;
use Made\CmsPackage\Content\Models\Page;

final class UpdateSitemapPage
{
    use AsAction;

    /**
     * Changes how Google sees a page from the screen of the sitemap (UpdatePageMeta) and, when that changed the page,
     * builds the sitemaps at once: the screen it comes back to reads them, and a rebuild after the answer could still
     * be on its way in another process. That rebuild then finds nothing new and stores nothing.
     */
    public function handle(Page $page, PageMetaChanges $changes): Page
    {
        $page = UpdatePageMeta::run($page, $changes);

        if ($page->wasChanged()) {
            BuildSitemaps::run(new SitemapReason(SitemapCause::Meta, $page->id));
        }

        return $page;
    }
}
