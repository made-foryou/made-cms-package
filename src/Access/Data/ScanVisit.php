<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

use Made\CmsPackage\Access\Models\Editor;

/**
 * A request to the scan of the site, by the logged-in editor or by a guest.
 */
final readonly class ScanVisit
{
    public function __construct(
        public ?Editor $editor,
    ) {}
}
