<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * A file as the media library describes it next to its list (DescribeMedia): the file, the places it is used and its
 * history (who uploaded it, its version and the version that waits to be put back).
 */
final readonly class MediaDescription
{
    public function __construct(
        public MediaFile $file,
        public MediaPlaces $places,
        public MediaHistory $history,
    ) {}
}
