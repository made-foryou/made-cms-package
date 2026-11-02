<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Models\Page;

final class FindPublishedPage
{
    use AsAction;

    /**
     * The page a visitor sees at an address: only a published page, never a draft.
     * Showing the draft to an editor in the edit mode is a decision of its own, not a flag here.
     */
    public function handle(PagePath $path): ?Page
    {
        return Page::query()->published()->atPath($path)->first();
    }
}
