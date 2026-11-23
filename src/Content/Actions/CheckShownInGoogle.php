<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;

final class CheckShownInGoogle
{
    use AsAction;

    /**
     * Whether Google shows the page now: visitors see it, its switch lets Google show it and it is not hidden (a hidden
     * page is noindex, as DescribePageMeta says). What Google makes of its title and description matters only then.
     */
    public function handle(Page $page): bool
    {
        return $page->status === PageStatus::Published && $page->indexable && ! $page->hidden;
    }
}
