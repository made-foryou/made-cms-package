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
     * @throws ValidationException keyed by "name" for a name without letters or digits
     * @throws MediaDiskNotUsable
     * @throws MediaNotStored
     */
    public function handle(Media $media, string $name): Media
    {
        $name = (MediaName::tryFrom($name)
            ?? throw ValidationException::withMessages(['name' => (string) __('made-cms-package::media.errors.name')]))->value;

        if ($media->name === $name) {
            return $media;
        }

        $disk = CheckMediaDisk::run();
        $extension = pathinfo($media->path, PATHINFO_EXTENSION);
        $path = dirname($media->path)."/{$name}".($extension === '' ? '' : ".{$extension}");

        if (! $disk->copy($media->path, $path)) {
            throw MediaNotStored::at($path);
        }

        try {
            return DB::transaction(static function () use ($media, $name, $path, $disk): Media {
                $stored = Media::query()->whereKey($media->id)->lockForUpdate()->firstOrFail();
                $former = $stored->path;

                $stored->update(['name' => $name, 'path' => $path]);
                MediaRenamed::dispatch($stored);

                DB::afterCommit(static function () use ($disk, $former, $path): void {
                    if ($former !== $path) {
                        $disk->delete($former);
                    }
                });

                return $stored;
            });
        } catch (Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }
    }
}
