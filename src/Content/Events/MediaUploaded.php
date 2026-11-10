<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Made\CmsPackage\Content\Models\Media;

final class MediaUploaded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Media $media,
    ) {}
}
