<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * A file as the media library describes it next to its list (DescribeMedia): the file and the places it is used.
 */
final readonly class MediaDescription
{
    public function __construct(
        public MediaFile $file,
        public MediaPlaces $places,
    ) {}
}
