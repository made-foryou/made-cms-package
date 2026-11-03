<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

use Made\CmsPackage\Access\Models\Editor;

/**
 * The visitor of a host page, reduced to what decides what the CMS launcher shows.
 */
final readonly class LauncherVisitor
{
    public function __construct(
        public ?string $ip,
        public bool $remembered,
        public ?Editor $editor = null,
        /** The page is shown in the edit mode, inside the frame of the CMS. */
        public bool $editing = false,
    ) {}
}
