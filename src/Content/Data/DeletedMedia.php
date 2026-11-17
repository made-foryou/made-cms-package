<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Models\Media;

/**
 * What deleting files of the media library did (DeleteMedia): the files it deleted, the files it kept because a
 * page uses them, and the ids of files that were gone already.
 */
final readonly class DeletedMedia
{
    /**
     * @param  list<Media>  $deleted  the files that left the library, as they were, in the order of their ids
     * @param  list<Media>  $kept  the files a page uses, in the order of their ids
     * @param  list<int>  $missing  the ids of files that were not in the library
     */
    public function __construct(
        public array $deleted = [],
        public array $kept = [],
        public array $missing = [],
    ) {}
}
