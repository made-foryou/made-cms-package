<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The part of a photo an editor keeps, as the editor sees the photo: its original upright, turned clockwise by the
 * rotation, then mirrored left to right when flipped. The area is in whole pixels of that view.
 *
 * The view of an original of W × H is H × W after a quarter turn. onOriginal() takes the area back onto the upright
 * original: first unmirrored (x becomes Vw − x − w), then unturned
 *
 *   0°   (x, y, w, h)
 *   90°  (y, H − x − w, h, w)
 *   180° (W − x − w, H − y − h, w, h)
 *   270° (W − y − h, x, h, w)
 *
 * so the original is cut first and only the cut is turned and mirrored, which gives the same pixels as cutting the
 * view (CropImage). The edit step does the same sums the other way round.
 */
final readonly class CropArea
{
    /** @var list<int> the turns an editor makes, in degrees clockwise */
    public const array ROTATIONS = [0, 90, 180, 270];

    /**
     * @param  int  $x  from the left of the view, in pixels
     * @param  int  $y  from the top of the view, in pixels
     * @param  int  $rotation  degrees clockwise, one of ROTATIONS
     * @param  bool  $flip  whether the turned original is mirrored left to right
     */
    public function __construct(
        public int $x,
        public int $y,
        public int $width,
        public int $height,
        public int $rotation = 0,
        public bool $flip = false,
    ) {}

    /**
     * The whole original as it is: not turned and not mirrored.
     */
    public static function whole(int $width, int $height): self
    {
        return new self(0, 0, $width, $height);
    }

    /**
     * The size of an upright original of this size as the editor sees it after the rotation.
     *
     * @return array{0: int, 1: int}
     */
    public static function turned(int $width, int $height, int $rotation): array
    {
        return $rotation === 90 || $rotation === 270 ? [$height, $width] : [$width, $height];
    }

    /**
     * The area as it was stored; null for anything that is no area.
     */
    public static function fromArray(mixed $values): ?self
    {
        if (! is_array($values)) {
            return null;
        }

        $x = $values['x'] ?? null;
        $y = $values['y'] ?? null;
        $width = $values['width'] ?? null;
        $height = $values['height'] ?? null;
        $rotation = $values['rotation'] ?? 0;
        $flip = $values['flip'] ?? false;

        if (! is_int($x) || ! is_int($y) || ! is_int($width) || ! is_int($height) || ! is_bool($flip)
            || ! in_array($rotation, self::ROTATIONS, true)) {
            return null;
        }

        return new self($x, $y, $width, $height, $rotation, $flip);
    }

    /**
     * Whether the area lies inside an upright original of this size, as the editor sees it, and has a size.
     */
    public function fits(int $width, int $height): bool
    {
        if (! in_array($this->rotation, self::ROTATIONS, true)) {
            return false;
        }

        [$viewWidth, $viewHeight] = self::turned($width, $height, $this->rotation);

        return $this->x >= 0 && $this->y >= 0 && $this->width >= 1 && $this->height >= 1
            && $this->x + $this->width <= $viewWidth && $this->y + $this->height <= $viewHeight;
    }

    /**
     * Whether the area is the whole upright original of this size, not turned and not mirrored.
     */
    public function isOriginal(int $width, int $height): bool
    {
        return $this->rotation === 0 && ! $this->flip
            && $this->x === 0 && $this->y === 0 && $this->width === $width && $this->height === $height;
    }

    /**
     * The part of the upright original of this size the area holds, before it is turned and mirrored.
     *
     * @return array{x: int, y: int, width: int, height: int}
     */
    public function onOriginal(int $width, int $height): array
    {
        [$viewWidth] = self::turned($width, $height, $this->rotation);
        $x = $this->flip ? $viewWidth - $this->x - $this->width : $this->x;

        return match ($this->rotation) {
            90 => ['x' => $this->y, 'y' => $height - $x - $this->width, 'width' => $this->height, 'height' => $this->width],
            180 => ['x' => $width - $x - $this->width, 'y' => $height - $this->y - $this->height, 'width' => $this->width, 'height' => $this->height],
            270 => ['x' => $width - $this->y - $this->height, 'y' => $x, 'width' => $this->height, 'height' => $this->width],
            default => ['x' => $x, 'y' => $this->y, 'width' => $this->width, 'height' => $this->height],
        };
    }

    /**
     * @return array{x: int, y: int, width: int, height: int, rotation: int, flip: bool}
     */
    public function toArray(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
            'width' => $this->width,
            'height' => $this->height,
            'rotation' => $this->rotation,
            'flip' => $this->flip,
        ];
    }
}
