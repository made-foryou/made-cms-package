<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Made\CmsPackage\Access\Models\Editor;

final class EditorCreated
{
    use Dispatchable;

    public function __construct(
        public readonly Editor $editor,
    ) {}
}
