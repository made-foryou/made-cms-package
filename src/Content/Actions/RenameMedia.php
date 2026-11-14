<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MediaName;
use Made\CmsPackage\Content\Events\MediaRenamed;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Exceptions\MediaNotStored;
use Made\CmsPackage\Content\Models\Media;
use Throwable;

final class RenameMedia
{
    use AsAction;

    /**
     * Gives a file of the library another name, the name search engines read (MediaName: a slug of lowercase letters,
     * digits and hyphens). The file the site shows gets it on the disk, in the folder it is in, with its own
     * extension; its original and thumbnail keep their names. It gets another address, which every page that shows
     * it takes at once; the former address stops working once the change is committed. The name stays when the
     * typed name gives the same slug.
     *
     * It works from the row as it is now, read again with its row locked, never from the model it is handed: a crop
     * or a new name stored after the editor opened the file is what it renames, and a crop at the same time waits.
     * The former file leaves through RemoveReplacedMediaFiles, which keeps it when the row holds it again.
     *
     * @throws ValidationException keyed by "name" for a name without letters or digits
     * @throws MediaDiskNotUsable
     * @throws MediaNotStored
     */
    public function handle(Media $media, string $name): Media
    {
        $name = (MediaName::tryFrom($name)
            ?? throw ValidationException::withMessages(['name' => (string) __('made-cms-package::media.errors.name')]))->value;

        $disk = CheckMediaDisk::run();
        $copy = null;

        try {
            return DB::transaction(static function () use ($media, $name, $disk, &$copy): Media {
                $stored = Media::query()->whereKey($media->id)->lockForUpdate()->firstOrFail();

                if ($stored->name === $name) {
                    return $stored;
                }

                $former = $stored->path;
                $extension = pathinfo($former, PATHINFO_EXTENSION);
                $path = dirname($former)."/{$name}".($extension === '' ? '' : ".{$extension}");

                if ($path !== $former) {
                    // A file already at that name (a name given back within one transaction, before its removal) holds
                    // the same photo; it is written over, but never removed when this fails.
                    $existed = $disk->exists($path);

                    if (! $disk->copy($former, $path)) {
                        throw MediaNotStored::at($path);
                    }

                    $copy = $existed ? null : $path;
                }

                $stored->update(['name' => $name, 'path' => $path]);
                MediaRenamed::dispatch($stored);

                if ($path !== $former) {
                    DB::afterCommit(static function () use ($stored, $former): void {
                        RemoveReplacedMediaFiles::run($stored->id, $former);
                    });
                }

                return $stored;
            });
        } catch (Throwable $exception) {
            if ($copy !== null) {
                $disk->delete($copy);
            }

            throw $exception;
        }
    }
}
