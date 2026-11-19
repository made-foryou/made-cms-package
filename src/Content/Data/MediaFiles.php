<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\MediaKind;

/**
 * The files of the media library that strips use, found at once (FindMedia), so rendering a page asks for them once.
 * A file that is not in it is gone: the strip that uses it renders without it.
 */
final readonly class MediaFiles
{
    /**
     * @param  array<int, MediaFile>  $files  keyed by id
     */
    public function __construct(
        public array $files = [],
    ) {}

    public function find(int $id): ?MediaFile
    {
        return $this->files[$id] ?? null;
    }

    /**
     * The file when it is an image; a document or a video is no image to show.
     */
    public function image(int $id): ?MediaFile
    {
        $file = $this->find($id);

        return $file?->kind === MediaKind::Image ? $file : null;
    }

    /**
     * The file when it is of one of the kinds; another kind is no file to link to there.
     *
     * @param  list<MediaKind>  $kinds
     */
    public function file(int $id, array $kinds): ?MediaFile
    {
        $file = $this->find($id);

        return $file !== null && in_array($file->kind, $kinds, true) ? $file : null;
    }
}
