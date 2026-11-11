<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * One page of a list of the media library: its files in the order of the list, which page it is, the number of files
 * the list holds in all and whether a next page follows.
 */
final readonly class MediaList
{
    /**
     * @param  list<MediaFile>  $files
     */
    public function __construct(
        public array $files,
        public int $page,
        public int $total,
        public bool $more,
    ) {}
}
