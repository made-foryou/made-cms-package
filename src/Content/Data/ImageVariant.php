<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * A narrower size of a photo, for the srcset of the site (MakeImageVariants): a file of its own on the media disk, next
 * to the photo it was made from, named after its width ({width}w.{format}) and never after the name of the file.
 *
 * @phpstan-type StoredVariant array{path: string, width: int, height: int}
 */
final readonly class ImageVariant
{
    /**
     * @param  string  $path  the file on the media disk
     * @param  int  $width  in pixels
     * @param  int  $height  in pixels
     */
    public function __construct(
        public string $path,
        public int $width,
        public int $height,
    ) {}

    /**
     * The sizes of a photo as the column `variants` stores them, from narrow to wide as they were made: null when they
     * were not made (a file from before the sizes, or no photo), an empty list when the photo needs none. Anything in
     * it that is no size is left out.
     *
     * @return list<self>|null
     */
    public static function listFromArray(mixed $values): ?array
    {
        if (! is_array($values)) {
            return null;
        }

        $variants = [];

        foreach ($values as $value) {
            if (is_array($value)
                && is_string($value['path'] ?? null)
                && is_int($value['width'] ?? null)
                && is_int($value['height'] ?? null)) {
                $variants[] = new self($value['path'], $value['width'], $value['height']);
            }
        }

        return $variants;
    }

    /**
     * The sizes as the column `variants` stores them.
     *
     * @param  list<self>|null  $variants
     * @return list<StoredVariant>|null
     */
    public static function listToArray(?array $variants): ?array
    {
        return $variants === null ? null : array_map(static fn (self $variant): array => $variant->toArray(), $variants);
    }

    /**
     * @return StoredVariant
     */
    public function toArray(): array
    {
        return ['path' => $this->path, 'width' => $this->width, 'height' => $this->height];
    }
}
