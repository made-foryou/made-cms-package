<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * How an image measures up to what a shared link shows of it: 1200 by 630 pixels, 1,91 : 1.
 */
final readonly class ShareImageFit
{
    /**
     * @param  bool  $tooSmall  apps show it small or not at all
     * @param  int  $cut  the percentage of the image a shared link cuts away in its middle, 0 for a shape that fits
     * @param  string|null  $axis  where it cuts: y at the top and the bottom, x at the sides; null when it cuts nothing
     */
    public function __construct(
        public bool $tooSmall,
        public int $cut,
        public ?string $axis,
    ) {}

    /**
     * @return array{too_small: bool, cut: int, axis: string|null}
     */
    public function toArray(): array
    {
        return [
            'too_small' => $this->tooSmall,
            'cut' => $this->cut,
            'axis' => $this->axis,
        ];
    }
}
