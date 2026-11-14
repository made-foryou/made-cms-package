<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Models\Media;

final class RemoveReplacedMediaFiles
{
    use AsAction;

    /**
     * Takes the files a change of a file of the library replaced off the disk, run by that change from DB::afterCommit
     * (CropImage, RenameMedia): the former photo and, when given, the former thumbnail. A former photo in a folder of
     * its own (a crop, {home}/{8 random}/{name}) goes with that folder unless the photo is in it now; one next to the
     * original goes alone.
     *
     * It reads the row again with its row locked and leaves every file the row holds now: a name given back within
     * one transaction, or a change committed after the one that replaced the file, points at it again. A change that
     * takes the lock meanwhile waits, so it never copies onto a file this removes. A row that is gone holds no file.
     *
     * @throws MediaDiskNotUsable
     */
    public function handle(int $mediaId, string $photo, ?string $thumb = null): void
    {
        $disk = CheckMediaDisk::run();

        DB::transaction(static function () use ($disk, $mediaId, $photo, $thumb): void {
            $media = Media::query()->whereKey($mediaId)->lockForUpdate()->first();
            $holds = $media === null ? [] : [$media->path, $media->thumb_path, $media->original_path];

            if (! in_array($photo, $holds, true)) {
                self::inFolderOfItsOwn($photo, $media)
                    ? $disk->deleteDirectory(dirname($photo))
                    : $disk->delete($photo);
            }

            if ($thumb !== null && ! in_array($thumb, $holds, true)) {
                $disk->delete($thumb);
            }
        });
    }

    /**
     * Whether a former photo is in a folder of a crop, directly in the home of the file next to its original, that the
     * photo of the row is not in now. Never the home itself.
     */
    private static function inFolderOfItsOwn(string $photo, ?Media $media): bool
    {
        if ($media === null || $media->original_path === null) {
            return false;
        }

        $home = dirname($media->original_path);
        $folder = dirname($photo);

        return $folder !== $home && dirname($folder) === $home && dirname($media->path) !== $folder;
    }
}
