<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * What making the missing narrower sizes of the photos of the media library did (MakeMissingVariants).
 */
final readonly class MadeVariants
{
    /**
     * @param  int  $made  the photos whose sizes were made (none for a photo narrower than every size)
     * @param  int  $had  the photos that had them already
     * @param  list<array{id: int, name: string, reason: 'missing'|'unreadable'}>  $failed  the photos that got none: their
     *                                                                                      file is not on the media disk
     *                                                                                      (missing), or no photo that
     *                                                                                      can be read (unreadable)
     */
    public function __construct(
        public int $made,
        public int $had,
        public array $failed,
    ) {}
}
