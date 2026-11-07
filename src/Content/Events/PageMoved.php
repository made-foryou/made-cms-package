<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Models\Page;

final class PageMoved implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  PagePath  $from  the address before; the same as $to when the address did not change
     */
    public function __construct(
        public readonly Page $page,
        public readonly PagePath $from,
        public readonly PagePath $to,
    ) {}
}
