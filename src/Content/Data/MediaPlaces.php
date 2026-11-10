<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The places a file of the media library is used (ListMediaPlaces): the pages by title, the places of a page in the
 * order of its strips. The library shows them as "Gebruikt op 2 pagina's"; a file with places cannot be deleted.
 */
final readonly class MediaPlaces
{
    /**
     * @param  list<MediaPlace>  $places
     */
    public function __construct(
        public array $places = [],
    ) {}

    public function count(): int
    {
        return count($this->places);
    }

    public function isEmpty(): bool
    {
        return $this->places === [];
    }

    /**
     * The pages the file is on, a deleted page too.
     */
    public function pageCount(): int
    {
        return count(array_unique(array_map(static fn (MediaPlace $place): int => $place->page, $this->places)));
    }
}
