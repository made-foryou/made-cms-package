<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MediaVersion;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Models\Media;

final class RemoveReplacedMediaFiles
{
    use AsAction;

    /**
     * Takes the files a change of a file of the library replaced off the disk, run by that change from DB::afterCommit
     * (CropImage, RenameMedia): the former photo and, when given, the former thumbnail. A former photo in a folder of
     * a crop ({home}/{8 random}/{name}, where home is the folder of the original of a version) goes with that folder;
     * one next to an original goes alone.
     *
     * It reads the row again with its row locked and leaves every file the row holds now, of the version the site
     * shows and of the one that waits to be put back: a name given back within one transaction, a change committed
     * after the one that replaced the file, or a version put back meanwhile points at it again. A folder that holds
     * such a file stays as a whole, so the folder of a version is never taken for the folder of a crop. A change that
     * takes the lock meanwhile waits, so it never copies onto a file this removes. A row that is gone holds no file.
     *
     * @throws MediaDiskNotUsable
     */
    public function handle(int $mediaId, string $photo, ?string $thumb = null): void
    {
        $disk = CheckMediaDisk::run();

        DB::transaction(static function () use ($disk, $mediaId, $photo, $thumb): void {
            $media = Media::query()->whereKey($mediaId)->lockForUpdate()->first();
            $holds = $media === null ? [] : MediaVersion::held($media);

            if (! in_array($photo, $holds, true)) {
                $folder = self::cropFolder($photo, $media, $holds);

                $folder === null
                    ? $disk->delete($photo)
                    : $disk->deleteDirectory($folder);
            }

            if ($thumb !== null && ! in_array($thumb, $holds, true)) {
                $disk->delete($thumb);
            }
        });
    }

    /**
     * The folder of a crop a former photo is in: directly in the home of a version of the row (the folder of its
     * original), not such a home itself, and holding no file the row holds now. Null for a photo next to an original.
     *
     * @param  list<string>  $holds
     */
    private static function cropFolder(string $photo, ?Media $media, array $holds): ?string
    {
        if ($media === null) {
            return null;
        }

        $homes = array_map(
            static fn (MediaVersion $version): string => $version->home(),
            array_filter(
                [MediaVersion::current($media), MediaVersion::waiting($media)],
                static fn (?MediaVersion $version): bool => $version?->originalPath !== null,
            ),
        );
        $folder = dirname($photo);

        if (in_array($folder, $homes, true) || ! in_array(dirname($folder), $homes, true)) {
            return null;
        }

        foreach ($holds as $held) {
            if (str_starts_with($held, "{$folder}/")) {
                return null;
            }
        }

        return $folder;
    }
}
