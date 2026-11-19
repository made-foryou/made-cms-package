<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\ShareImageFit;

final class MeasureShareImage
{
    use AsAction;

    /** The size a shared link shows an image at best, 1,91 : 1. */
    public const int WIDTH = 1200;

    public const int HEIGHT = 630;

    /** Below this size apps show the image small or not at all. */
    public const int MIN_WIDTH = 600;

    public const int MIN_HEIGHT = 315;

    /** A shape that loses no more than this percentage counts as fitting. */
    public const int TOLERANCE = 2;

    /**
     * Measures an image against what a shared link shows of it: too small, and how much of another shape the link
     * cuts away in the middle and where. It never refuses anything. The card in `share.js` measures the same way.
     */
    public function handle(?int $width, ?int $height): ShareImageFit
    {
        if ($width === null || $height === null || $width < 1 || $height < 1) {
            return new ShareImageFit(tooSmall: false, cut: 0, axis: null);
        }

        $wanted = self::WIDTH / self::HEIGHT;
        $shape = $width / $height;
        $axis = $shape > $wanted ? 'x' : 'y';
        $cut = (int) round((1 - ($axis === 'x' ? $wanted / $shape : $shape / $wanted)) * 100);
        $fits = $cut <= self::TOLERANCE;

        return new ShareImageFit(
            tooSmall: $width < self::MIN_WIDTH || $height < self::MIN_HEIGHT,
            cut: $fits ? 0 : $cut,
            axis: $fits ? null : $axis,
        );
    }
}
