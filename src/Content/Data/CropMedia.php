<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * How an editor wants a photo cut from its original.
 */
final readonly class CropMedia
{
    /**
     * @param  CropArea  $area  the part of the original to keep, as the editor saw it
     * @param  float  $focalX  the point to keep in view, from 0 (left) to 1 (right) of the photo that comes out
     * @param  float  $focalY  the point to keep in view, from 0 (top) to 1 (bottom) of the photo that comes out
     * @param  bool  $scaleDown  whether the photo is scaled down to media.image.max_width; off keeps the size of the
     *                           area, still in media.image.format
     */
    public function __construct(
        public CropArea $area,
        public float $focalX = 0.5,
        public float $focalY = 0.5,
        public bool $scaleDown = true,
    ) {}
}
