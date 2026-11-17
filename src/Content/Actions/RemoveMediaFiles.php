<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;

final class RemoveMediaFiles
{
    use AsAction;

    /**
     * Takes the folder of a deleted file off the media disk, media/{ulid} with every version and crop in it, run by
     * DeleteMedia from DB::afterCommit. Only the folder of one file: anything that is no ulid is refused, so an empty
     * or broken key never takes the whole library with it.
     *
     * @throws InvalidArgumentException for a key that is no ulid
     * @throws MediaDiskNotUsable
     */
    public function handle(string $ulid): void
    {
        if (preg_match('/^[0-9a-z]{26}$/', $ulid) !== 1) {
            throw new InvalidArgumentException("[{$ulid}] is not the folder of a media file.");
        }

        CheckMediaDisk::run()->deleteDirectory("media/{$ulid}");
    }
}
