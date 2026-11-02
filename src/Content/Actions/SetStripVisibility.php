<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Events\StripVisibilityChanged;
use Made\CmsPackage\Content\Exceptions\StripNotFound;
use Made\CmsPackage\Content\Models\Page;

final class SetStripVisibility
{
    use AsAction;

    /**
     * Shows or hides a strip in the draft; a hidden strip keeps its place and values.
     *
     * @throws StripNotFound
     */
    public function handle(Page $page, string $stripId, bool $visible): Strip
    {
        $strips = PageStrips::fromArray($page->draft_strips ?? $page->strips);
        $strip = ($strips->find($stripId) ?? throw StripNotFound::id($page, $stripId))->withVisibility($visible);

        $page->draft_strips = $strips->replace($strip)->toArray();
        $page->save();

        StripVisibilityChanged::dispatch($page, $strip);

        return $strip;
    }
}
