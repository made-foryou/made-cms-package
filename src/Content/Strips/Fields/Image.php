<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips\Fields;

use Closure;
use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Content\Data\MediaFiles;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Models\Media;

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
        return [$path => [$this->presence(), self::file()]];
    }

    /**
     * The id of the file as a number; any other value (of a former field type, an address of the scan) is no image.
     */
    public function normalize(mixed $value): ?int
    {
        return self::id($value);
    }

    public function places(mixed $value, string $path): array
    {
        $id = self::id($value);

        return $id === null ? [] : [$path => $id];
    }

    /**
     * @return RenderedImage|null
     */
    public function render(mixed $value, ?MediaFiles $media = null): ?array
    {
        $id = self::id($value);

        return $id === null ? null : $media?->image($id)?->image();
    }

    /**
     * The rule that wants the id of an image in the library: a number of a file that is still there and is an image.
     */
    private static function file(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            $id = self::id($value);

            if ($id === null) {
                $fail(self::message('image'));

                return;
            }

            $kind = Media::query()->whereKey($id)->value('kind');

            if ($kind === null) {
                $fail(self::message('image_missing'));
            } elseif ($kind !== MediaKind::Image) {
                $fail(self::message('image_kind'));
            }
        };
    }

    /**
     * The id of a file: a whole number above zero, also as text.
     */
    private static function id(mixed $value): ?int
    {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        return is_int($value) && $value > 0 ? $value : null;
    }

    private static function message(string $key): string
    {
        return (string) __('made-cms-package::strips.validation.'.$key);
    }
}
