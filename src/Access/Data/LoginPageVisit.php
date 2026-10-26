<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

use Made\CmsPackage\Access\Models\Editor;

/**
 * A visit to the login page, reduced to what decides the step it opens on.
 */
final readonly class LoginPageVisit
{
    public function __construct(
        public ?Editor $editor,
        public bool $arrived,
    ) {}
}
