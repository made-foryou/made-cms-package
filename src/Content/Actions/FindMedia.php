<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Content\Data\MediaFiles;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Models\Media;

final class FindMedia
{
    use AsAction;

    /**
     * The files of the media library with these ids, in one query, each with the url a visitor opens it at; never
     * the place on the disk or the original of a photo. A file that is gone is left out. Without ids it asks nothing
     * of the database or the disk, so a site without images never needs the media disk.
     *
     * @param  list<int>  $ids
     *
     * @throws MediaDiskNotUsable when there is a file to find and the media disk gives no url a visitor can open
     */
    public function handle(array $ids): MediaFiles
    {
        if ($ids === []) {
            return new MediaFiles;
        }

        $disk = CheckMediaDisk::run();
        $files = [];

        foreach (Media::query()->with('folder')->whereKey(array_values(array_unique($ids)))->get() as $media) {
            $files[$media->id] = new MediaFile(
                id: $media->id,
                kind: $media->kind,
                url: $disk->url($media->path),
                name: $media->name,
                mime: $media->mime,
                size: $media->size,
                width: $media->width,
                height: $media->height,
                alt: $media->alt,
                focalX: $media->focal_x,
                focalY: $media->focal_y,
                folder: $media->folder?->name,
            );
        }

        return new MediaFiles($files);
    }
}
