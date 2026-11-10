<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * What building the usage of the media library again found (RebuildMediaUsage).
 */
final readonly class RebuiltMediaUsage
{
    /**
     * @param  int  $pages  the pages read, deleted pages too
     * @param  int  $places  the places of a file afterwards, in every version of every page
     */
    public function __construct(
        public int $pages,
        public int $places,
    ) {}
}
