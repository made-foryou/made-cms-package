<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Exceptions\PageAlreadyPublished;
use Made\CmsPackage\Content\Models\Page;

final class PublishPage
{
    use AsAction;

    /**
     * Makes the draft, as it is stored now, the published version of the page, with the editor who published it
     * (PutDraftLive): it keeps it as the next version of the page and takes a planned publication along.
     *
     * @throws PageAlreadyPublished when the page is published and has no draft
     * @throws ValidationException keyed by "strips.<strip id>.<field path>"
     */
    public function handle(Page $page, ?int $editorId = null): Page
    {
        ChangeDraft::run($page, static function (PageStrips $strips, Page $page) use ($editorId): void {
            if ($page->status === PageStatus::Published && $page->draft_strips === null) {
                throw PageAlreadyPublished::for($page);
            }

            PutDraftLive::run($page, $strips, $editorId);
        }, $editorId);

        return $page;
    }
}
