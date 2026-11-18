<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips;

use Closure;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Models\Media;

/**
 * A value that holds a file of the media library: the id of the file, the same for an image and a document or video,
 * and the rule that wants such a file of the kinds a field takes. A field that holds a file stores only its id, so the
 * file changes everywhere it is used.
 */
final class MediaChoice
{
    /**
     * The id of a file: a whole number above zero, also as text. Any other value (of a former field type, an address of
     * the scan) holds no file.
     */
    public static function id(mixed $value): ?int
    {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        return is_int($value) && $value > 0 ? $value : null;
    }

    /**
     * The rule that wants the id of a file in the library of one of the kinds: a number of a file that is still there
     * and is of such a kind. The messages are keys of the strip validation texts.
     *
     * @param  list<MediaKind>  $kinds
     */
    public static function rule(array $kinds, string $choose, string $missing, string $kind): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($kinds, $choose, $missing, $kind): void {
            $id = self::id($value);

            if ($id === null) {
                $fail(self::message($choose));

                return;
            }

            $stored = Media::query()->whereKey($id)->value('kind');

            if ($stored === null) {
                $fail(self::message($missing));
            } elseif (! in_array($stored, $kinds, true)) {
                $fail(self::message($kind));
            }
        };
    }

    private static function message(string $key): string
    {
        return (string) __('made-cms-package::strips.validation.'.$key);
    }
}
