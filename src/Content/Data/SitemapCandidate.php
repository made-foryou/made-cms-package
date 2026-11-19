<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Carbon\CarbonImmutable;

/**
 * Something a source of the sitemap holds to be in it: its name, its path on the site (without the prefix of the
 * route group; null when it has no address) and when what it shows last changed.
 */
final readonly class SitemapCandidate
{
    public function __construct(
        public string $label,
        public ?string $path,
        public ?CarbonImmutable $lastModified,
    ) {}
}
