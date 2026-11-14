<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Models\MediaFolder;

/**
 * The media library as its screen opens (OpenMediaLibrary): the filter it opens with, the folder it is in, the first
 * page of the files, and the folders with their numbers and those of the whole library.
 */
final readonly class MediaLibrary
{
    /**
     * @param  MediaFilter  $filter  the filter of the list, without a folder that is gone
     * @param  MediaFolder|null  $folder  the folder the list is in; null for "Alle bestanden"
     */
    public function __construct(
        public MediaFilter $filter,
        public ?MediaFolder $folder,
        public MediaList $list,
        public MediaFolders $folders,
    ) {}
}
