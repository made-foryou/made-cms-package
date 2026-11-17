<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Carbon\CarbonImmutable;
use League\Flysystem\FilesystemException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\CropArea;
use Made\CmsPackage\Content\Data\MediaCrop;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Exceptions\MediaNotCroppable;
use Made\CmsPackage\Content\Models\Media;

final class DescribeMediaCrop
{
    use AsAction;

    /**
     * A photo as the crop step shows it: the photo, the size of its original, how it was cut from it (the whole
     * original when it never was, or when the stored crop no longer fits it), and the pages that show it, because a
     * crop changes it there at once.
     *
     * @throws MediaNotCroppable for a file without an original, or whose original has no known size
     * @throws MediaDiskNotUsable
     */
    public function handle(Media $media): MediaCrop
    {
        $width = $media->original_width;
        $height = $media->original_height;

        if ($media->kind !== MediaKind::Image || $media->original_path === null || $width === null || $height === null) {
            throw MediaNotCroppable::id($media->id);
        }

        $disk = CheckMediaDisk::run();
        $file = FindMedia::run([$media->id])->find($media->id) ?? throw MediaNotCroppable::id($media->id);
        $area = CropArea::fromArray($media->crop);

        try {
            $size = $disk->size($media->original_path);
        } catch (FilesystemException) {
            $size = null;
        }

        return new MediaCrop(
            file: $file,
            folderId: $media->folder_id,
            originalWidth: $width,
            originalHeight: $height,
            originalSize: $size,
            originalExtension: strtolower(pathinfo($media->original_path, PATHINFO_EXTENSION)),
            area: $area !== null && $area->fits($width, $height) ? $area : CropArea::whole($width, $height),
            scaleDown: ($media->crop['scale_down'] ?? true) !== false,
            pages: ListMediaPlaces::run($media)->pageCount(withDeleted: false),
            // A replaced photo was uploaded when its version was placed: the original is the one of that version.
            uploadedAt: $media->replaced_at ?? ($media->created_at === null ? null : CarbonImmutable::instance($media->created_at)),
        );
    }
}
