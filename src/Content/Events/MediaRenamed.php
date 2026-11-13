<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Made\CmsPackage\Content\Models\Media;

/**
 * A file of the library got another name, and with it another address.
 */
final class MediaRenamed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Media $media,
    ) {}
}
