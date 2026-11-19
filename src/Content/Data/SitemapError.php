<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\SitemapErrorReason;

/**
 * An address that belongs in the sitemap but is left out of it: its path as visitors open it (null without an
 * address), its name and why.
 */
final readonly class SitemapError
{
    public function __construct(
        public ?string $path,
        public string $label,
        public SitemapErrorReason $reason,
    ) {}

    /**
     * @return array{path: string|null, label: string, reason: string}
     */
    public function toArray(): array
    {
        return ['path' => $this->path, 'label' => $this->label, 'reason' => $this->reason->value];
    }
}
