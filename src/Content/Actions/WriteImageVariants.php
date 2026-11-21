<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Contracts\Filesystem\Cloud;
use Intervention\Image\Exceptions\DecoderException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\ImageVariant;
use Made\CmsPackage\Content\Enums\MediaFileRole;
use Made\CmsPackage\Content\Exceptions\ImageDriverNotSupported;
use Made\CmsPackage\Content\Exceptions\MediaNotStored;
use Throwable;

final class WriteImageVariants
{
    use AsAction;

    /**
     * Makes the narrower sizes of a photo (MakeImageVariants) and writes them into the folder of the photo as
     * {width}w.{format}, never after the name of the file, so a new name leaves them as they are and never shows a
     * former name. They are written as MediaFileRole::Variant says: public, with the cache header of a file whose bytes
     * never change at its address. Each folder a photo is written to is new (an upload, a version, a crop), so a size
     * never overwrites one a visitor opened. When one cannot be written, the ones it wrote leave the disk again.
     *
     * @param  string  $directory  the folder of the photo on the media disk
     * @param  string  $photo  the contents of the photo the site shows
     * @return list<ImageVariant> from narrow to wide; empty when the photo is narrower than every size
     *
     * @throws DecoderException when the contents are no image
     * @throws ImageDriverNotSupported
     * @throws MediaNotStored
     */
    public function handle(Cloud $disk, string $directory, string $photo): array
    {
        $written = [];

        try {
            foreach (MakeImageVariants::run($photo) as $variant) {
                $path = "{$directory}/{$variant->width}w.{$variant->extension}";

                if (! $disk->put($path, (string) $variant->contents, MediaFileRole::Variant->writeOptions())) {
                    throw MediaNotStored::at($path);
                }

                $written[] = new ImageVariant($path, $variant->width, $variant->height);
            }
        } catch (Throwable $exception) {
            foreach ($written as $variant) {
                $disk->delete($variant->path);
            }

            throw $exception;
        }

        return $written;
    }
}
