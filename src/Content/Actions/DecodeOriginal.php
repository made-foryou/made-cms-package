<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Validation\ValidationException;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\Interfaces\ImageInterface;
use League\Flysystem\UnableToReadFile;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Enums\ImageDriver;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Exceptions\ImageDriverNotSupported;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Exceptions\MediaNotCroppable;
use Made\CmsPackage\Content\Models\Media;

final class DecodeOriginal
{
    use AsAction;

    /**
     * The original of a photo as it was uploaded, turned upright by its EXIF orientation and without the metadata of
     * the camera: the image a crop is cut from and the crop step shows whole. Only a photo that was scaled down keeps
     * one.
     *
     * @throws MediaNotCroppable for a file without an original
     * @throws ValidationException keyed by "crop" when the original is not on the disk or cannot be read
     * @throws MediaDiskNotUsable
     * @throws ImageDriverNotSupported
     */
    public function handle(Media $media): ImageInterface
    {
        if ($media->kind !== MediaKind::Image || $media->original_path === null) {
            throw MediaNotCroppable::id($media->id);
        }

        $disk = CheckMediaDisk::run();

        try {
            $contents = $disk->get($media->original_path);
        } catch (UnableToReadFile) {
            $contents = null;
        }

        try {
            return ImageDriver::configured()->manager()->decodeBinary($contents ?? throw new DecoderException);
        } catch (DecoderException) {
            throw ValidationException::withMessages(['crop' => (string) __('made-cms-package::media.errors.original_missing')]);
        }
    }
}
