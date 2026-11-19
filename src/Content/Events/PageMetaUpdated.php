<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Made\CmsPackage\Content\Models\Page;

/**
 * The title, the description, the sitemap switch or the noindex switch of a page changed.
 */
final class PageMetaUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Page $page,
    ) {}
}
