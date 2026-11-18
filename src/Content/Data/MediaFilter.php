<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Enums\MediaSort;

/**
 * What a list of the media library shows: the files of a folder (or all of them), whose name or alt text holds the
 * search, of a kind or of several kinds (the picker of a file field: documents and videos), only the images without
 * an alt text, in an order, and which page of them.
 */
final readonly class MediaFilter
{
    /**
     * @param  list<MediaKind>  $kinds  the kinds it lists; when not empty they win from $kind
     */
    public function __construct(
        public ?int $folderId = null,
        public string $search = '',
        public ?MediaKind $kind = null,
        public bool $withoutAlt = false,
        public MediaSort $sort = MediaSort::Newest,
        public int $page = 1,
        public array $kinds = [],
    ) {}
}
