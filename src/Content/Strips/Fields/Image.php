<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips\Fields;

use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Content\Data\MediaFiles;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Strips\MediaChoice;

/**
 * An image of the media library, stored as the id of its file, so a file changes everywhere it is used.
 *
 * It renders as {url, alt, width, height, focal}: the alt text belongs to the file, never to the place. A file that is
 * gone, or that is no image, renders as null, so the site shows its own fallback; saving or publishing the strip
 * refuses it. An image has no default, because the id of a file differs per database.
 *
 * @phpstan-import-type RenderedImage from MediaFile
 */
final class Image extends Field
{
    public static function make(string $key): self
    {
        return new self($key);
    }

    public function type(): string
    {
        return 'image';
    }

    public function rules(string $path): array
    {
        return [$path => [$this->presence(), MediaChoice::rule([MediaKind::Image], 'image', 'image_missing', 'image_kind')]];
    }

    /**
     * The id of the file as a number; any other value (of a former field type, an address of the scan) is no image.
     */
    public function normalize(mixed $value): ?int
    {
        return MediaChoice::id($value);
    }

    public function places(mixed $value, string $path): array
    {
        $id = MediaChoice::id($value);

        return $id === null ? [] : [$path => $id];
    }

    /**
     * @return RenderedImage|null
     */
    public function render(mixed $value, ?MediaFiles $media = null): ?array
    {
        $id = MediaChoice::id($value);

        return $id === null ? null : $media?->image($id)?->image();
    }
}
