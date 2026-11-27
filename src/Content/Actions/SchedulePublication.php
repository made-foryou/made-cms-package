<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Events\PublicationScheduled;
use Made\CmsPackage\Content\Exceptions\PublicationMomentPassed;
use Made\CmsPackage\Content\Models\Page;

final class SchedulePublication
{
    use AsAction;

    /**
     * Plans the publication of the page at a moment, in whole minutes: made-cms:publish-scheduled then puts the draft
     * live as it is by that moment (PublishScheduledPage). The plan belongs to the page, not to one draft, so an
     * editor may choose the moment before making the changes. It runs through ChangeDraft, so it follows a
     * publication at the same moment instead of crossing it.
     *
     * The moment the page is planned at already keeps the plan, also once it passed, so saving the basics of a page
     * with a plan that is late never fails; another moment lies after the present minute. A new plan forgets a
     * planned publication a strip stopped (publish_failed_at).
     *
     * @throws PublicationMomentPassed
     */
    public function handle(Page $page, CarbonImmutable $at, ?int $editorId = null): Page
    {
        // Eloquent writes the time of the moment as it reads, so in the timezone of the app.
        $moment = $at->setTimezone(date_default_timezone_get())->startOfMinute();

        ChangeDraft::run($page, static function (PageStrips $strips, Page $page) use ($moment, $editorId): void {
            if ($page->publish_at?->equalTo($moment) === true) {
                return;
            }

            if ($moment->lessThanOrEqualTo(now())) {
                throw PublicationMomentPassed::at($moment);
            }

            $page->publish_at = $moment;
            $page->scheduled_by = $editorId;
            $page->publish_failed_at = null;

            PublicationScheduled::dispatch($page);
        }, $editorId);

        return $page;
    }
}
