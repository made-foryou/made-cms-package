<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Made\CmsPackage\Content\Models\MediaFolder;

/**
 * A folder of the media library got another name; its files stay in it.
 */
final class MediaFolderRenamed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly MediaFolder $folder,
    ) {}
}
