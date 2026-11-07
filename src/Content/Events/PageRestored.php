<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Models\Page;

final class PageRestored implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  PagePath  $path  the address the page got back
     */
    public function __construct(
        public readonly Page $page,
        public readonly PagePath $path,
    ) {}
}
