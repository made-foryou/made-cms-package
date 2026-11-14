<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The number of files of the whole media library, of each kind, and of the images without an alt text: the numbers of
 * the filters of the library, whatever it lists.
 */
final readonly class MediaCounts
{
    public function __construct(
        public int $all,
        public int $image,
        public int $document,
        public int $video,
        public int $withoutAlt,
    ) {}

    /**
     * @return array{all: int, image: int, document: int, video: int, without_alt: int}
     */
    public function toArray(): array
    {
        return [
            'all' => $this->all,
            'image' => $this->image,
            'document' => $this->document,
            'video' => $this->video,
            'without_alt' => $this->withoutAlt,
        ];
    }
}
