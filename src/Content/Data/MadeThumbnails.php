<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * What making the missing thumbnails of the media library did (MakeMissingThumbnails).
 */
final readonly class MadeThumbnails
{
    /**
     * @param  int  $made  the photos that got a thumbnail
     * @param  int  $had  the photos that had one already
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
