<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Models\Media;

final class FindLinkedMedia
{
    use AsAction;

    /**
     * The address a visitor opens a file of the media library at now, found by the key of its lasting link: the ulid
     * of its folder on the disk, which stays while the file is replaced, cropped or renamed. Null for a key of no file,
     * a file that left the library too.
     *
     * @throws MediaDiskNotUsable
     */
    public function handle(string $ulid): ?string
    {
        if (preg_match('/^[0-9a-z]{26}$/', $ulid) !== 1) {
            return null;
        }

        $media = Media::query()->linkedBy($ulid)->first();

        return $media === null ? null : CheckMediaDisk::run()->url($media->path);
    }
}
