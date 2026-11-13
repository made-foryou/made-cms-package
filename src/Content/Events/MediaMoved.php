<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Made\CmsPackage\Content\Models\Media;

/**
 * A file of the library went into another folder, or out of its folder.
 */
final class MediaMoved implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Media $media,
    ) {}
}
