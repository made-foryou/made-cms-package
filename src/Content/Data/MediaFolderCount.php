<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * A folder of the media library with the number of its files, as a list of folders shows it.
 */
final readonly class MediaFolderCount
{
    public function __construct(
        public int $id,
        public string $name,
        public int $count,
    ) {}

    /**
     * @return array{id: int, name: string, count: int}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'count' => $this->count];
    }
}
