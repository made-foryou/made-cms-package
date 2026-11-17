<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MediaVersion;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Models\Media;

final class RemoveMediaVersionFiles
{
    use AsAction;

    /**
     * Takes the files of a version a replacement pushed out off the disk, run by ReplaceMedia from DB::afterCommit. A
     * version in a folder of its own (media/{ulid}/{8 random}) goes with that folder; the upload at the root of the
     * folder of the file goes file by file, with the folder of its crop.
     *
     * It reads the row again with its row locked and leaves every file the row holds now, and every folder that holds
     * one, like RemoveReplacedMediaFiles. It never touches anything outside media/{ulid} of the row. A row that is
     * gone holds nothing any more: deleting it took its folder off the disk.
     *
     * @throws MediaDiskNotUsable
     */
    public function handle(int $mediaId, MediaVersion $version): void
    {
        $disk = CheckMediaDisk::run();

        DB::transaction(static function () use ($disk, $mediaId, $version): void {
            $media = Media::query()->whereKey($mediaId)->lockForUpdate()->first();

            if ($media === null) {
                return;
            }

            $root = "media/{$media->ulid}";
            $holds = MediaVersion::held($media);
            $home = $version->home();

            if (self::folderOfItsOwn($home, $root)) {
                if (! self::holdsIn($holds, $home)) {
                    $disk->deleteDirectory($home);
                }

                return;
            }

            foreach ($version->files() as $file) {
                if (str_starts_with($file, "{$root}/") && ! in_array($file, $holds, true)) {
                    $disk->delete($file);
                }
            }

            $crop = dirname($version->path);

            if ($version->originalPath !== null && $home === $root && self::folderOfItsOwn($crop, $root) && ! self::holdsIn($holds, $crop)) {
                $disk->deleteDirectory($crop);
            }
        });
    }

    /**
     * Whether a folder lies directly in the folder of the file, and is not that folder itself.
     */
    private static function folderOfItsOwn(string $folder, string $root): bool
    {
        return $folder !== $root && dirname($folder) === $root;
    }

    /**
     * @param  list<string>  $holds
     */
    private static function holdsIn(array $holds, string $folder): bool
    {
        foreach ($holds as $held) {
            if (str_starts_with($held, "{$folder}/")) {
                return true;
            }
        }

        return false;
    }
}
