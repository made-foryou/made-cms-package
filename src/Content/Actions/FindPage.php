<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Models\Page;

final class FindPage
{
    use AsAction;

    /**
     * The page at an address whatever its status, a draft too: for the edit mode and the CMS.
     * A visitor only ever gets FindPublishedPage.
     */
    public function handle(PagePath $path): ?Page
    {
        return Page::query()->atPath($path)->first();
    }
}
