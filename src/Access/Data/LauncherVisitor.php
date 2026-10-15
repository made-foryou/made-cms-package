<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

/**
 * The visitor of a host page, reduced to what decides whether the CMS launcher is shown.
 */
final readonly class LauncherVisitor
{
    public function __construct(
        public ?string $ip,
        public bool $remembered,
    ) {}
}
