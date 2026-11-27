<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Enums\ScheduledPublicationOutcome;
use Made\CmsPackage\Content\Events\ScheduledPublicationFailed;
use Made\CmsPackage\Content\Exceptions\PublicationRefused;
use Made\CmsPackage\Content\Models\Page;

final class PublishScheduledPage
{
    use AsAction;

    /**
     * Puts the draft of the page live for the publication planned at the moment, by the editor who planned it, as
     * PublishPage does (PutDraftLive). It reads the page again with its row locked (ChangeDraft) and only publishes
     * while the page is still planned at that moment: a plan taken away, moved or published by hand in the meantime
     * stays as it is. A page live without a draft has nothing to publish and only loses its plan.
     *
     * A strip that stops the publication leaves the draft and stops the plan: the moment goes to publish_failed_at
     * (ScheduledPublicationFailed), so the page asks for attention and never goes live once it is half fixed.
     */
    public function handle(Page $page, CarbonImmutable $moment): ScheduledPublicationOutcome
    {
        return ChangeDraft::run($page, static function (PageStrips $strips, Page $page) use ($moment): ScheduledPublicationOutcome {
            if ($page->publish_at?->equalTo($moment) !== true) {
                return ScheduledPublicationOutcome::Moved;
            }

            if ($page->status === PageStatus::Published && $page->draft_strips === null) {
                $page->publish_at = null;
                $page->scheduled_by = null;

                return ScheduledPublicationOutcome::NothingToPublish;
            }

            try {
                PutDraftLive::run($page, $strips, $page->scheduled_by);
            } catch (PublicationRefused) {
                $page->publish_at = null;
                $page->scheduled_by = null;
                $page->publish_failed_at = $moment;

                ScheduledPublicationFailed::dispatch($page);

                return ScheduledPublicationOutcome::Refused;
            }

            return ScheduledPublicationOutcome::Published;
        }, $page->scheduled_by);
    }
}
