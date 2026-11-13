<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Carbon\CarbonImmutable;

/**
 * A photo as the crop step shows it (DescribeMediaCrop): the file, its original and how it was cut from it.
 */
final readonly class MediaCrop
{
    /**
     * @param  MediaFile  $file  the photo as the site shows it now
     * @param  int|null  $folderId  the folder it is in; null for none
     * @param  int  $originalWidth  the upright width of the original, the view a crop is drawn on before it turns
     * @param  int  $originalHeight  the upright height of the original
     * @param  int|null  $originalSize  the bytes of the original on the disk; null when the disk cannot tell
     * @param  string  $originalExtension  the extension of the original as it was uploaded, such as "jpg"
     * @param  CropArea  $area  how the photo was cut from it; the whole original when it never was
     * @param  bool  $scaleDown  whether the photo was scaled down to media.image.max_width
     * @param  int  $pages  the pages that show the photo, in their draft or their published version, without the deleted ones
     */
    public function __construct(
        public MediaFile $file,
        public ?int $folderId,
        public int $originalWidth,
        public int $originalHeight,
        public ?int $originalSize,
        public string $originalExtension,
        public CropArea $area,
        public bool $scaleDown,
        public int $pages,
        public ?CarbonImmutable $uploadedAt,
    ) {}
}
