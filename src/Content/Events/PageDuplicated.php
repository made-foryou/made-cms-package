<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Made\CmsPackage\Content\Models\Page;

final class PageDuplicated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  Page  $page  the copy, a draft
     * @param  Page  $original  the page it was copied from
     */
    public function __construct(
        public readonly Page $page,
        public readonly Page $original,
    ) {}
}
