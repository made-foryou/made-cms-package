<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Models\Media;
use Throwable;

/**
 * One version of a file of the media library: the files it holds on the media disk and the columns that describe them.
 * The row holds the version the site shows in its own columns and the version that waits to be put back in
 * `previous`, in the form toArray() gives; putting one back swaps the two (RestoreMediaVersion).
 *
 * A version that came in by replacing the file lives in a folder of its own, media/{ulid}/{8 random}; the upload
 * lives at the root, media/{ulid}. A crop of a version goes next to its original, in {home}/{8 random}. The narrower
 * sizes of a photo (ImageVariant) lie next to the photo they were made from.
 */
final readonly class MediaVersion
{
    /**
     * @param  int  $version  its number: 1 for the upload, each replacement the highest so far plus one
     * @param  string  $name  the slug of the file name without extension
     * @param  string  $path  the file the site shows, on the media disk
     * @param  string|null  $originalPath  the upload as it came in, next to a photo that was scaled down
     * @param  string|null  $thumbPath  the small picture of a photo that was scaled down
     * @param  array<string, mixed>|null  $crop  how the photo was cut from its original; null for the whole original
     * @param  int|null  $placedBy  the editor who placed this version by replacing the file; null for the upload
     * @param  CarbonImmutable|null  $placedAt  when this version was placed by replacing the file; null for the upload
     * @param  list<ImageVariant>|null  $variants  the narrower sizes of the photo; null while they were not made
     */
    public function __construct(
        public int $version,
        public string $name,
        public string $path,
        public ?string $originalPath,
        public ?string $thumbPath,
        public string $mime,
        public int $size,
        public ?int $width,
        public ?int $height,
        public ?int $originalWidth,
        public ?int $originalHeight,
        public ?float $focalX,
        public ?float $focalY,
        public ?array $crop,
        public ?int $placedBy,
        public ?CarbonImmutable $placedAt,
        public ?array $variants = null,
    ) {}

    /**
     * The version the site shows now, from the columns of the row.
     */
    public static function current(Media $media): self
    {
        return new self(
            version: $media->version,
            name: $media->name,
            path: $media->path,
            originalPath: $media->original_path,
            thumbPath: $media->thumb_path,
            mime: $media->mime,
            size: $media->size,
            width: $media->width,
            height: $media->height,
            originalWidth: $media->original_width,
            originalHeight: $media->original_height,
            focalX: $media->focal_x,
            focalY: $media->focal_y,
            crop: $media->crop,
            placedBy: $media->replaced_by,
            placedAt: $media->replaced_at,
            variants: ImageVariant::listFromArray($media->variants),
        );
    }

    /**
     * The version that waits to be put back; null when nothing waits.
     */
    public static function waiting(Media $media): ?self
    {
        return self::fromArray($media->previous);
    }

    /**
     * Every file the row holds: those of the version the site shows and of the version that waits to be put back.
     *
     * @return list<string>
     */
    public static function held(Media $media): array
    {
        return [...self::current($media)->files(), ...self::waiting($media)?->files() ?? []];
    }

    /**
     * The version as `previous` holds it; null for anything that is no version.
     */
    public static function fromArray(mixed $values): ?self
    {
        if (! is_array($values)
            || ! is_int($values['version'] ?? null)
            || ! is_string($values['name'] ?? null)
            || ! is_string($values['path'] ?? null)
            || ! is_string($values['mime'] ?? null)
            || ! is_int($values['size'] ?? null)) {
            return null;
        }

        $placedAt = $values['placed_at'] ?? null;

        if ($placedAt !== null) {
            if (! is_string($placedAt)) {
                return null;
            }

            try {
                $placedAt = CarbonImmutable::parse($placedAt);
            } catch (Throwable) {
                return null;
            }
        }

        $crop = $values['crop'] ?? null;

        return new self(
            version: $values['version'],
            name: $values['name'],
            path: $values['path'],
            originalPath: self::text($values['original_path'] ?? null),
            thumbPath: self::text($values['thumb_path'] ?? null),
            mime: $values['mime'],
            size: $values['size'],
            width: self::number($values['width'] ?? null),
            height: self::number($values['height'] ?? null),
            originalWidth: self::number($values['original_width'] ?? null),
            originalHeight: self::number($values['original_height'] ?? null),
            focalX: self::fraction($values['focal_x'] ?? null),
            focalY: self::fraction($values['focal_y'] ?? null),
            crop: is_array($crop) ? $crop : null,
            placedBy: self::number($values['placed_by'] ?? null),
            placedAt: $placedAt,
            variants: ImageVariant::listFromArray($values['variants'] ?? null),
        );
    }

    /**
     * The version as `previous` stores it.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'name' => $this->name,
            'path' => $this->path,
            'original_path' => $this->originalPath,
            'thumb_path' => $this->thumbPath,
            'mime' => $this->mime,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'original_width' => $this->originalWidth,
            'original_height' => $this->originalHeight,
            'focal_x' => $this->focalX,
            'focal_y' => $this->focalY,
            'crop' => $this->crop,
            'placed_by' => $this->placedBy,
            'placed_at' => $this->placedAt?->toIso8601String(),
            'variants' => ImageVariant::listToArray($this->variants),
        ];
    }

    /**
     * The columns of the row that make this the version the site shows.
     *
     * @return array<string, mixed>
     */
    public function columns(): array
    {
        return [
            'version' => $this->version,
            'name' => $this->name,
            'path' => $this->path,
            'original_path' => $this->originalPath,
            'thumb_path' => $this->thumbPath,
            'mime' => $this->mime,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'original_width' => $this->originalWidth,
            'original_height' => $this->originalHeight,
            'focal_x' => $this->focalX,
            'focal_y' => $this->focalY,
            'crop' => $this->crop,
            'replaced_by' => $this->placedBy,
            'replaced_at' => $this->placedAt,
            'variants' => ImageVariant::listToArray($this->variants),
        ];
    }

    /**
     * The files of this version on the media disk: the file the site shows, its thumbnail, its original and its
     * narrower sizes.
     *
     * @return list<string>
     */
    public function files(): array
    {
        return [
            ...array_values(array_filter(
                [$this->path, $this->thumbPath, $this->originalPath],
                static fn (?string $path): bool => $path !== null,
            )),
            ...$this->variantPaths(),
        ];
    }

    /**
     * The files of the narrower sizes of the photo, from narrow to wide.
     *
     * @return list<string>
     */
    public function variantPaths(): array
    {
        return array_map(static fn (ImageVariant $variant): string => $variant->path, $this->variants ?? []);
    }

    /**
     * The folder this version lives in: the one of its original, else the one of the file itself. Its crops are in
     * folders of their own inside it.
     */
    public function home(): string
    {
        return dirname($this->originalPath ?? $this->path);
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private static function number(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    private static function fraction(mixed $value): ?float
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
    }
}
