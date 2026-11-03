<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Made\CmsPackage\Content\Models\Page;

final class PageCreated
{
    use Dispatchable;

    public function __construct(
        public readonly Page $page,
    ) {}
}
