<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MediaDownload;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Exceptions\MediaFileMissing;
use Made\CmsPackage\Content\Models\Media;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

final class OpenMediaDownload
{
    use AsAction;

    /**
     * A file of the media library to save, read through the app from the media disk, never through its address: of
     * a photo that was scaled down its original as it was uploaded (full size, its own type, not cropped), which is
     * private; of any other file the file itself. It is saved as {name}.{extension}.
     *
     * @throws MediaFileMissing when the file is not on the disk
     * @throws MediaDiskNotUsable
     */
    public function handle(Media $media): MediaDownload
    {
        $disk = CheckMediaDisk::run();
        $path = $media->original_path ?? $media->path;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        try {
            $stream = $disk->exists($path) ? $disk->readStream($path) : null;
            $size = is_resource($stream) ? $disk->size($path) : 0;
        } catch (Throwable) {
            $stream = null;
            $size = 0;
        }

        if (! is_resource($stream)) {
            throw MediaFileMissing::at($path);
        }

        return new MediaDownload(
            stream: $stream,
            filename: "{$media->name}.{$extension}",
            mime: $media->original_path === null
                ? $media->mime
                : (MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? 'application/octet-stream'),
            size: $size,
        );
    }
}
