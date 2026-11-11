<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The folders of the media library from a to z with their numbers of files, and the number of all files
 * ("Alle bestanden"), also those in no folder.
 */
final readonly class MediaFolders
{
    /**
     * @param  list<MediaFolderCount>  $folders
     */
    public function __construct(
        public array $folders,
        public int $all,
    ) {}

    /**
     * @return array{all: int, folders: list<array{id: int, name: string, count: int}>}
     */
    public function toArray(): array
    {
        return [
            'all' => $this->all,
            'folders' => array_map(static fn (MediaFolderCount $folder): array => $folder->toArray(), $this->folders),
        ];
    }
}
