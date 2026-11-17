<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MediaName;
use Made\CmsPackage\Content\Data\MediaVersion;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Events\MediaReplaced;
use Made\CmsPackage\Content\Exceptions\ImageDriverNotSupported;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Exceptions\MediaNotStored;
use Made\CmsPackage\Content\Models\Media;
use RuntimeException;
use Throwable;

final class ReplaceMedia
{
    use AsAction;

    /**
     * Gives a file of the media library a new version under the same id, so it changes on every page that shows it at
     * once, the published ones too: strips store the id only. The upload is checked and stored as a new file is
     * (CheckUploadedMedia, ScaleImage, StoreMediaFiles), in a folder of its own (media/{ulid}/{8 random}), so no cache
     * keeps the former version. Only a file of the same kind replaces it, or an image field on a page would break.
     *
     * The new version takes the name of the upload, has no crop or focal point and counts the highest number so far
     * plus one; the alt text, the folder and who uploaded the file first stay. The version the site showed waits to
     * be put back (RestoreMediaVersion) and is made private once the change is committed (HidePreviousVersion); a
     * version that waited already leaves the disk then (RemoveMediaVersionFiles).
     *
     * It works from the row as it is now, read again with its row locked, like a crop and a new name: a change at the
     * same time waits until this one is committed. The new folder leaves the disk again when the row cannot be stored.
     *
     * @throws ValidationException keyed by "file" for a file the library does not take or of another kind
     * @throws ModelNotFoundException when the file is gone
     * @throws MediaDiskNotUsable
     * @throws ImageDriverNotSupported
     * @throws MediaNotStored
     */
    public function handle(Media $media, UploadedMedia $upload): Media
    {
        $disk = CheckMediaDisk::run();
        $checked = CheckUploadedMedia::run($upload->path);

        if ($checked->kind !== $media->kind) {
            throw ValidationException::withMessages([
                'file' => (string) __("made-cms-package::media.errors.replace_kind.{$media->kind->value}"),
            ]);
        }

        if (preg_match('/^[0-9a-z]{26}$/', $media->ulid) !== 1) {
            throw new RuntimeException("The media file [{$media->id}] has no folder of its own on the disk.");
        }

        $image = $checked->kind === MediaKind::Image && $checked->mime !== 'image/svg+xml'
            ? ScaleImage::run($upload->path, $checked)
            : null;
        $name = MediaName::fromFilename($upload->filename, $checked->kind)->value;
        $directory = "media/{$media->ulid}/".Str::lower(Str::random(8));

        try {
            $columns = StoreMediaFiles::run($disk, $directory, $name, $upload->path, $checked, $image);
        } catch (Throwable $exception) {
            $disk->deleteDirectory($directory);

            throw $exception;
        }

        // The new folder leaves again only when the row cannot be stored, from inside the transaction, never around
        // it: a callback after the commit that throws must not take the files of a stored row away.
        return DB::transaction(static function () use ($disk, $media, $directory, $name, $columns, $upload): Media {
            try {
                $stored = Media::query()->whereKey($media->id)->lockForUpdate()->firstOrFail();
                $pushed = MediaVersion::waiting($stored);
                $previous = MediaVersion::current($stored);

                $stored->update([
                    ...$columns,
                    'name' => $name,
                    'focal_x' => null,
                    'focal_y' => null,
                    'crop' => null,
                    'version' => max($previous->version, $pushed->version ?? 0) + 1,
                    'replaced_by' => $upload->uploadedBy,
                    'replaced_at' => now()->toImmutable(),
                    'previous' => $previous->toArray(),
                ]);
                MediaReplaced::dispatch($stored);
            } catch (Throwable $exception) {
                $disk->deleteDirectory($directory);

                throw $exception;
            }

            self::afterCommit(static fn () => HidePreviousVersion::run($stored->id));

            if ($pushed !== null) {
                self::afterCommit(static fn () => RemoveMediaVersionFiles::run($stored->id, $pushed));
            }

            return $stored;
        });
    }

    /**
     * Runs a cleanup once the change is committed. The row holds the new version by then, so a cleanup that fails is
     * reported and never fails the replacement, nor stops the next cleanup.
     */
    private static function afterCommit(Closure $cleanup): void
    {
        DB::afterCommit(static function () use ($cleanup): void {
            try {
                $cleanup();
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
