<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * What building the sitemap of a route group gave: how many addresses and errors it holds, and whether it changed.
 */
final readonly class BuiltSitemap
{
    public function __construct(
        public string $prefix,
        public int $addresses,
        public int $errors,
        public bool $changed,
    ) {}
}
