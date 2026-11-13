<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Events\MediaMoved;
use Made\CmsPackage\Content\Exceptions\MediaFolderNotFound;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;

final class MoveMedia
{
    use AsAction;

    /**
     * Puts a file of the library into a folder, or into none ("Alle bestanden") for null. Only the row changes: a
     * folder of the library is no folder on the disk, so the address of the file stays.
     *
     * @throws MediaFolderNotFound
     */
    public function handle(Media $media, ?int $folderId): Media
    {
        $folder = $folderId === null
            ? null
            : MediaFolder::query()->find($folderId) ?? throw MediaFolderNotFound::id($folderId);

        if ($media->folder_id === $folder?->id) {
            return $media;
        }

        $media->folder_id = $folder?->id;
        $media->save();

        MediaMoved::dispatch($media);

        return $media;
    }
}
