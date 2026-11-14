<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\DB;
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
     * It compares with the folder the file is in now, read again with its row locked, never with the model it is
     * handed: a file another editor moved after this one opened it still goes where this one chose. The folder is
     * read with a shared lock, so it cannot be removed before the file is in it.
     *
     * @throws MediaFolderNotFound
     */
    public function handle(Media $media, ?int $folderId): Media
    {
        return DB::transaction(static function () use ($media, $folderId): Media {
            $folder = $folderId === null
                ? null
                : MediaFolder::query()->whereKey($folderId)->sharedLock()->first() ?? throw MediaFolderNotFound::id($folderId);

            $stored = Media::query()->whereKey($media->id)->lockForUpdate()->firstOrFail();

            if ($stored->folder_id === $folder?->id) {
                return $stored;
            }

            $stored->update(['folder_id' => $folder?->id]);
            MediaMoved::dispatch($stored);

            return $stored;
        });
    }
}
