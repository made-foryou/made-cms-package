<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Enums\MediaSort;

/**
 * What a list of the media library shows: the files of a folder (or all of them), whose name or alt text holds the
 * search, of a kind, only the images without an alt text, in an order, and which page of them.
 */
final readonly class MediaFilter
{
    public function __construct(
        public ?int $folderId = null,
        public string $search = '',
        public ?MediaKind $kind = null,
        public bool $withoutAlt = false,
        public MediaSort $sort = MediaSort::Newest,
        public int $page = 1,
    ) {}
}
