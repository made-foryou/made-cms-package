<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The versions a page keeps, the newest first, and whether putting one back would replace a draft.
 */
final readonly class PageHistory
{
    /**
     * @param  list<VersionSummary>  $versions
     * @param  bool  $draft  whether the page has a draft now, which putting a version back replaces
     */
    public function __construct(
        public array $versions,
        public bool $draft,
    ) {}

    /**
     * The editors who published a version, each once, for the screen to name them.
     *
     * @return list<int>
     */
    public function editors(): array
    {
        $ids = array_filter(array_map(static fn (VersionSummary $version): ?int => $version->publishedBy, $this->versions));

        return array_values(array_unique($ids));
    }
}
