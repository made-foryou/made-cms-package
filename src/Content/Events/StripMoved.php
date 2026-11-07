<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Models\Page;

final class StripMoved implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Page $page,
        public readonly Strip $strip,
        public readonly int $from,
        public readonly int $to,
    ) {}
}
