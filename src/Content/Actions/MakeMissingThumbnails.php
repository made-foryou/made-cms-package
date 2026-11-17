<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Support\Str;
use Intervention\Image\Exceptions\DecoderException;
use League\Flysystem\UnableToReadFile;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MadeThumbnails;
use Made\CmsPackage\Content\Enums\MediaFileRole;
use Made\CmsPackage\Content\Exceptions\ImageDriverNotSupported;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Exceptions\MediaNotStored;
use Made\CmsPackage\Content\Models\Media;

final class MakeMissingThumbnails
{
    use AsAction;

    /**
     * Gives every photo that was scaled down but has no thumbnail one (MakeThumbnail), made from the photo the site
     * shows: once on a site that had photos before the library made thumbnails. It goes next to the original, because
     * a cropped photo lies in a folder of its own that the next crop removes, and is written like the thumbnail of an
     * upload (MediaFileRole::Thumbnail: public, with its cache header). Each photo is read, made and written on its
     * own, so a photo whose file is gone or cannot be read is named and the others go on; a disk that does not take a
     * thumbnail stops it, because it would refuse the others too. A photo that got a thumbnail meanwhile keeps it, and
     * the one made here goes off the disk again.
     *
     * @throws MediaDiskNotUsable
     * @throws ImageDriverNotSupported
     * @throws MediaNotStored
     */
    public function handle(): MadeThumbnails
    {
        $disk = CheckMediaDisk::run();
        $had = Media::query()->scaled()->count() - Media::query()->withoutThumbnail()->count();
        $made = 0;
        $failed = [];

        foreach (Media::query()->withoutThumbnail()->lazyById(100) as $media) {
            $photo = $this->read($disk, $media->path);

            if ($photo === null) {
                $failed[] = ['id' => $media->id, 'name' => $media->name, 'reason' => 'missing'];

                continue;
            }

            try {
                $thumbnail = MakeThumbnail::run($photo);
            } catch (DecoderException) {
                $failed[] = ['id' => $media->id, 'name' => $media->name, 'reason' => 'unreadable'];

                continue;
            }

            $path = dirname((string) $media->original_path).'/thumb-'.Str::random(40).".{$thumbnail->extension}";

            if (! $disk->put($path, (string) $thumbnail->contents, MediaFileRole::Thumbnail->writeOptions())) {
                throw MediaNotStored::at($path);
            }

            if (Media::query()->withoutThumbnail()->whereKey($media->id)->update(['thumb_path' => $path]) === 0) {
                $disk->delete($path);

                continue;
            }

            $made++;
        }

        return new MadeThumbnails($made, $had, $failed);
    }

    /**
     * The contents of the photo the site shows; null when it is not on the disk.
     */
    private function read(Cloud $disk, string $path): ?string
    {
        try {
            return $disk->get($path);
        } catch (UnableToReadFile) {
            return null;
        }
    }
}
