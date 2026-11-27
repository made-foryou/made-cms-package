<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Events\ScheduledPublicationCancelled;
use Made\CmsPackage\Content\Models\Page;

final class CancelScheduledPublication
{
    use AsAction;

    /**
     * Takes the planned publication of the page away; the draft stays, to publish by hand or to plan again. A note
     * that a strip stopped a planned publication stays until the draft is published or thrown away. Without a plan
     * nothing happens.
     */
    public function handle(Page $page, ?int $editorId = null): Page
    {
        ChangeDraft::run($page, static function (PageStrips $strips, Page $page): void {
            if ($page->publish_at === null) {
                return;
            }

            $page->publish_at = null;
            $page->scheduled_by = null;

            ScheduledPublicationCancelled::dispatch($page);
        }, $editorId);

        return $page;
    }
}
