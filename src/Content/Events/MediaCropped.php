<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Made\CmsPackage\Content\Models\Media;

/**
 * A photo was cut from its original again, or got another focal point; it changed on every page that shows it.
 */
final class MediaCropped implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Media $media,
    ) {}
}
