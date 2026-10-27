<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;

/**
 * A request to open a screen of the CMS, by the logged-in editor or by a guest.
 */
final readonly class ScreenVisit
{
    public function __construct(
        public ?Editor $editor,
        public Screen $screen,
    ) {}
}
