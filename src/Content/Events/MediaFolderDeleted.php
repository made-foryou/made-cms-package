<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Made\CmsPackage\Content\Models\MediaFolder;

/**
 * An empty folder left the media library. The model keeps the id and name it had.
 */
final class MediaFolderDeleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly MediaFolder $folder,
    ) {}
}
