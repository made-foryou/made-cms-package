<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * One page of a list of the media library: its files in the order of the list, which page it is, the number of files
 * the list holds in all, whether a next page follows and which of its files a page uses.
 */
final readonly class MediaList
{
    /**
     * @param  list<MediaFile>  $files
     * @param  list<int>  $used  the ids of the files of this page a page uses, which cannot be deleted
     */
    public function __construct(
        public array $files,
        public int $page,
        public int $total,
        public bool $more,
        public array $used = [],
    ) {}
}
