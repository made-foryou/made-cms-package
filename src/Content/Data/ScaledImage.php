<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * An image as the site shows it.
 */
final readonly class ScaledImage
{
    /**
     * @param  int  $width  in pixels, upright
     * @param  int  $height  in pixels, upright
     * @param  string|null  $contents  the scaled image; null when the site shows the upload itself (an animation)
     * @param  string  $mime  the type of the contents, or of the upload when it is shown itself
     * @param  string  $extension  the extension that goes with the type
     * @param  int|null  $sourceWidth  the upright width of the image it was made from, before it was scaled down
     * @param  int|null  $sourceHeight  the upright height of the image it was made from, before it was scaled down
     */
    public function __construct(
        public int $width,
        public int $height,
        public ?string $contents,
        public string $mime,
        public string $extension,
        public ?int $sourceWidth = null,
        public ?int $sourceHeight = null,
    ) {}
}
