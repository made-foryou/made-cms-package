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
     * The edit mode finds a draft for an editor with FindPage instead; there is no flag here.
     */
    public function handle(PagePath $path): ?Page
    {
        return Page::query()->published()->atPath($path)->first();
    }
}
