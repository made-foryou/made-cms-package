<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Events\StripRemoved;
use Made\CmsPackage\Content\Exceptions\StripNotFound;
use Made\CmsPackage\Content\Models\Page;

final class RemoveStrip
{
    use AsAction;

    /**
     * Removes a strip from the draft; the published page keeps it until the draft is published.
     *
     * @throws StripNotFound
     */
    public function handle(Page $page, string $stripId): void
    {
        $strips = PageStrips::fromArray($page->draft_strips ?? $page->strips);
        $strip = $strips->find($stripId) ?? throw StripNotFound::id($page, $stripId);

        $page->draft_strips = $strips->remove($stripId)->toArray();
        $page->save();

        StripRemoved::dispatch($page, $strip);
    }
}
