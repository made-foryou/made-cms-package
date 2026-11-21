<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Exceptions\DecoderException;
use League\Flysystem\UnableToReadFile;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\ImageVariant;
use Made\CmsPackage\Content\Data\MadeVariants;
use Made\CmsPackage\Content\Data\MediaVersion;
use Made\CmsPackage\Content\Exceptions\ImageDriverNotSupported;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Exceptions\MediaNotStored;
use Made\CmsPackage\Content\Models\Media;

final class MakeMissingVariants
{
    use AsAction;

    /**
     * Gives every photo that was scaled down but whose narrower sizes were not made its sizes (WriteImageVariants),
     * made from the photo the site shows and written next to it: once on a site that had photos before the library
     * made them. A photo narrower than every size counts as made, without files. Each photo is read, made and written
     * on its own, so a photo whose file is gone or cannot be read is named and the others go on; a disk that does not
     * take a size stops it, because it would refuse the others too.
     *
     * A photo that was cropped, replaced or given its sizes meanwhile keeps what it has: the sizes made here are stored
     * only while the row still points at the photo they were made from, and otherwise leave the disk again, except a
     * file the row holds by then.
     *
     * @throws MediaDiskNotUsable
     * @throws ImageDriverNotSupported
     * @throws MediaNotStored
     */
    public function handle(): MadeVariants
    {
        $disk = CheckMediaDisk::run();
        $had = Media::query()->scaled()->count() - Media::query()->withoutVariants()->count();
        $made = 0;
        $failed = [];

        foreach (Media::query()->withoutVariants()->lazyById(100) as $media) {
            $photo = $this->read($disk, $media->path);

            if ($photo === null) {
                $failed[] = ['id' => $media->id, 'name' => $media->name, 'reason' => 'missing'];

                continue;
            }

            try {
                $variants = WriteImageVariants::run($disk, dirname($media->path), $photo);
            } catch (DecoderException) {
                $failed[] = ['id' => $media->id, 'name' => $media->name, 'reason' => 'unreadable'];

                continue;
            }

            if ($this->store($disk, $media, $variants)) {
                $made++;
            }
        }

        return new MadeVariants($made, $had, $failed);
    }

    /**
     * Stores the sizes on the row while it still points at the photo they were made from and has none; otherwise takes
     * them off the disk again, leaving a file the row holds now.
     *
     * @param  list<ImageVariant>  $variants
     */
    private function store(Cloud $disk, Media $media, array $variants): bool
    {
        return DB::transaction(static function () use ($disk, $media, $variants): bool {
            $stored = Media::query()->whereKey($media->id)->lockForUpdate()->first();

            if ($stored !== null && $stored->path === $media->path && $stored->variants === null) {
                $stored->update(['variants' => ImageVariant::listToArray($variants)]);

                return true;
            }

            $holds = $stored === null ? [] : MediaVersion::held($stored);

            foreach ($variants as $variant) {
                if (! in_array($variant->path, $holds, true)) {
                    $disk->delete($variant->path);
                }
            }

            return false;
        });
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
