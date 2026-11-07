<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Events\DraftDiscarded;
use Made\CmsPackage\Content\Models\Page;

final class DiscardDraft
{
    use AsAction;

    /**
     * Throws the draft away, as it is stored now, so the page is back at its published strips. Without a draft
     * nothing happens.
     */
    public function handle(Page $page): Page
    {
        ChangeDraft::run($page, static function (PageStrips $strips, Page $page): void {
            if ($page->draft_strips === null) {
                return;
            }

            $page->draft_strips = null;

            DraftDiscarded::dispatch($page);
        });

        return $page;
    }
}
