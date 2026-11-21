<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MediaVersion;
use Made\CmsPackage\Content\Enums\MediaFileRole;
use Made\CmsPackage\Content\Events\MediaVersionRestored;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Exceptions\MediaNotStored;
use Made\CmsPackage\Content\Models\Media;
use Throwable;

final class RestoreMediaVersion
{
    use AsAction;

    /**
     * Puts the version of a file that waits back: it swaps places with the version the site shows, which waits in its
     * turn, so putting it back again undoes it. The file changes on every page that shows it at once, the published
     * ones too. Nothing leaves the disk.
     *
     * The files of the version it puts back (the photo, its thumbnail and its narrower sizes) are made public by their
     * roles before the row points at them, so the site never shows a file a visitor cannot open; the files of the
     * version that now waits are made private once the change is committed (HidePreviousVersion). It works from the
     * row as it is now, read again with its row locked.
     *
     * @throws ValidationException keyed by "version" when no version waits
     * @throws ModelNotFoundException when the file is gone
     * @throws MediaNotStored when the disk does not open a file of the version it puts back
     * @throws MediaDiskNotUsable
     */
    public function handle(Media $media): Media
    {
        $disk = CheckMediaDisk::run();

        return DB::transaction(static function () use ($disk, $media): Media {
            $stored = Media::query()->whereKey($media->id)->lockForUpdate()->firstOrFail();
            $waiting = MediaVersion::waiting($stored)
                ?? throw ValidationException::withMessages(['version' => (string) __('made-cms-package::media.errors.no_previous')]);
            $shown = MediaVersion::current($stored);
            $opened = [];

            try {
                foreach (self::shownFiles($waiting) as $path => $role) {
                    if (! $disk->setVisibility($path, $role->visibility())) {
                        throw MediaNotStored::at($path);
                    }

                    $opened[] = $path;
                }

                $stored->update([...$waiting->columns(), 'previous' => $shown->toArray()]);
                MediaVersionRestored::dispatch($stored);
            } catch (Throwable $exception) {
                self::close($disk, $opened);

                throw $exception;
            }

            DB::afterCommit(static function () use ($stored): void {
                try {
                    HidePreviousVersion::run($stored->id);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });

            return $stored;
        });
    }

    /**
     * The files of a version a visitor opens, by their roles: the file the site shows, its thumbnail and its narrower
     * sizes. Its original stays private.
     *
     * @return array<string, MediaFileRole>
     */
    private static function shownFiles(MediaVersion $version): array
    {
        $files = [$version->path => MediaFileRole::shown($version->originalPath !== null)];

        if ($version->thumbPath !== null) {
            $files[$version->thumbPath] = MediaFileRole::Thumbnail;
        }

        foreach ($version->variantPaths() as $variant) {
            $files[$variant] = MediaFileRole::Variant;
        }

        return $files;
    }

    /**
     * Makes the files it opened private again when the version could not be put back after all.
     *
     * @param  list<string>  $paths
     */
    private static function close(Cloud $disk, array $paths): void
    {
        foreach ($paths as $path) {
            try {
                $disk->setVisibility($path, Filesystem::VISIBILITY_PRIVATE);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
