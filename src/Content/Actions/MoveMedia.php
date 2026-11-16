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
     * Puts one or more files of the library into a folder, or into none ("Alle bestanden") for null, all or none of
     * them. Only the rows change: a folder of the library is no folder on the disk, so the addresses of the files stay.
     * It gives the files as they are stored then, by id; a file that is gone is left out.
     *
     * It compares with the folder each file is in now, read again with the rows locked in the order of their ids (so
     * two moves at the same time wait for each other instead of locking each other out), never with a model it was
     * handed: a file another editor moved after this one chose it still goes where this one chose. The folder is read
     * with a shared lock, so it cannot be removed before the files are in it. Only a file that changes folder is told
     * as moved.
     *
     * @param  list<int>  $mediaIds
     * @return list<Media>
     *
     * @throws MediaFolderNotFound
     */
    public function handle(array $mediaIds, ?int $folderId): array
    {
        return DB::transaction(static function () use ($mediaIds, $folderId): array {
            $folder = $folderId === null
                ? null
                : MediaFolder::query()->whereKey($folderId)->sharedLock()->first() ?? throw MediaFolderNotFound::id($folderId);

            $stored = Media::query()->whereKey($mediaIds)->orderBy('id')->lockForUpdate()->get();

            foreach ($stored as $media) {
                if ($media->folder_id === $folder?->id) {
                    continue;
                }

                $media->update(['folder_id' => $folder?->id]);
                MediaMoved::dispatch($media);
            }

            return array_values($stored->all());
        });
    }
}
