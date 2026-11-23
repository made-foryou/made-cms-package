<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Events\StripRemoved;
use Made\CmsPackage\Content\Exceptions\StripNotFound;
use Made\CmsPackage\Content\Models\Page;

final class RemoveStrip
{
    use AsAction;

    /**
     * Removes a strip from the draft; the published page keeps it until the draft is published.
     * Returns the strip as it was stored, so the edit shell can put it back with RestoreStrip.
     *
     * @throws StripNotFound
     */
    public function handle(Page $page, string $stripId, ?int $editorId = null): Strip
    {
        return ChangeDraft::run($page, static function (PageStrips $strips, Page $page) use ($stripId): Strip {
            $strip = $strips->find($stripId) ?? throw StripNotFound::id($page, $stripId);

            $page->draft_strips = $strips->remove($stripId)->toArray();

            StripRemoved::dispatch($page, $strip);

            return $strip;
        }, $editorId);
    }
}
