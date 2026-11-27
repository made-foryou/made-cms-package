<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\ScheduledPublications;
use Made\CmsPackage\Content\Enums\ScheduledPublicationOutcome;
use Made\CmsPackage\Content\Models\Page;

final class PublishScheduledPages
{
    use AsAction;

    /**
     * Publishes every page whose planned moment came, the earliest first (PublishScheduledPage), each in a
     * transaction of its own, so a page a strip stops never holds up the others. A deleted page is never published,
     * also not one deleted in the meantime. made-cms:publish-scheduled runs it every minute, with the scheduler of
     * the host.
     */
    public function handle(?CarbonImmutable $now = null): ScheduledPublications
    {
        $now ??= now()->toImmutable();
        $published = 0;
        $refused = 0;

        foreach (Page::query()->dueForPublication($now)->get() as $page) {
            if ($page->publish_at === null) {
                continue;
            }

            try {
                $outcome = PublishScheduledPage::run($page, $page->publish_at);
            } catch (ModelNotFoundException) {
                continue;
            }

            $published += $outcome === ScheduledPublicationOutcome::Published ? 1 : 0;
            $refused += $outcome === ScheduledPublicationOutcome::Refused ? 1 : 0;
        }

        return new ScheduledPublications($published, $refused);
    }
}
