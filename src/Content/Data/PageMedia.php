<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\MediaKind;

/**
 * The files on the working copy of a page (ListPageMedia): the card "Bestanden op deze pagina" and the count in
 * "In het kort". Only the files of its strips; its share image lies outside them.
 */
final readonly class PageMedia
{
    /**
     * @param  list<MediaFile>  $files  each file once, in the order the page shows them
     * @param  list<int>  $withoutAlt  the ids of those files that need an alt text and have none, in the same order
     * @param  bool  $available  false when the media disk gives no address a visitor can open, so no file is shown
     */
    public function __construct(
        public array $files = [],
        public array $withoutAlt = [],
        public bool $available = true,
    ) {}

    /**
     * The number of files per kind, by the value of its MediaKind, every kind in the order of its cases.
     *
     * @return array<string, int>
     */
    public function countPerKind(): array
    {
        $counts = array_fill_keys(array_map(static fn (MediaKind $kind): string => $kind->value, MediaKind::cases()), 0);

        foreach ($this->files as $file) {
            $counts[$file->kind->value]++;
        }

        return $counts;
    }
}
