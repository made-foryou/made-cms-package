<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Data\RestoredVersion;
use Made\CmsPackage\Content\Events\PageVersionRestored;
use Made\CmsPackage\Content\Exceptions\PageVersionNotFound;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\PageVersion;

final class RestorePageVersion
{
    use AsAction;

    /**
     * Puts the strips of a version of the page back as its draft, so an editor sees them on the site first and then
     * publishes them; what visitors see does not change. A draft that was there is gone. The strips come back as the
     * version holds them, the level of a heading too and a strip whose type left the catalogue: they went live like
     * that. A file a strip held that is gone stays gone; publishing refuses it at its field. When the version is what
     * visitors see, there is no draft afterwards (ChangeDraft drops a draft without changes).
     *
     * @throws PageVersionNotFound when the version belongs to another page
     */
    public function handle(Page $page, PageVersion $version, ?int $editorId = null): RestoredVersion
    {
        if ($version->page_id !== $page->id) {
            throw PageVersionNotFound::on($page, $version);
        }

        $replacedDraft = ChangeDraft::run($page, static function (PageStrips $strips, Page $page) use ($version): bool {
            $replacedDraft = $page->draft_strips !== null;

            $page->draft_strips = PageStrips::fromArray($version->strips)->toArray();

            PageVersionRestored::dispatch($page, $version);

            return $replacedDraft;
        }, $editorId);

        return new RestoredVersion($version->number, $replacedDraft, $page->draft_strips !== null);
    }
}
