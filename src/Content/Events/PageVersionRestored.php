<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\PageVersion;

final class PageVersionRestored implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  Page  $page  the page whose draft now holds the strips of the version
     */
    public function __construct(
        public readonly Page $page,
        public readonly PageVersion $version,
    ) {}
}
