<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Made\CmsPackage\Content\Models\Media;

/**
 * A file got a new version under the same id (ReplaceMedia); it changed on every page that shows it, and the version it had waits to be put back.
 */
final class MediaReplaced implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Media $media,
    ) {}
}
