<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MediaVersion;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Exceptions\MediaNotStored;
use Made\CmsPackage\Content\Models\Media;
use Throwable;

final class HidePreviousVersion
{
    use AsAction;

    /**
     * Makes every file of the version that waits to be put back private, run from DB::afterCommit by ReplaceMedia and
     * RestoreMediaVersion: a replaced file is gone for a visitor at once, while the app keeps it to put it back. It
     * reads the row again with its row locked and leaves a file the version the site shows holds as well.
     *
     * The row is committed already, so a file the disk does not make private is reported and stays public: the site
     * never breaks on it, and the next version that waits is made private again.
     *
     * @throws MediaDiskNotUsable
     */
    public function handle(int $mediaId): void
    {
        $disk = CheckMediaDisk::run();

        DB::transaction(static function () use ($disk, $mediaId): void {
            $media = Media::query()->whereKey($mediaId)->lockForUpdate()->first();
            $waiting = $media === null ? null : MediaVersion::waiting($media);

            if ($media === null || $waiting === null) {
                return;
            }

            $shown = MediaVersion::current($media)->files();

            foreach ($waiting->files() as $file) {
                if (in_array($file, $shown, true)) {
                    continue;
                }

                try {
                    if (! $disk->setVisibility($file, Filesystem::VISIBILITY_PRIVATE)) {
                        throw MediaNotStored::at($file);
                    }
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        });
    }
}
