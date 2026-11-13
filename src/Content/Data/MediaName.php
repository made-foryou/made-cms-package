<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Illuminate\Support\Str;
use Made\CmsPackage\Content\Enums\MediaKind;

/**
 * The name of a file in the library and on the disk, the name search engines read: a slug of lowercase letters,
 * digits and hyphens, at most 100 characters, without extension.
 */
final readonly class MediaName
{
    public const int MAX_LENGTH = 100;

    private function __construct(public string $value) {}

    /**
     * The name of an upload: the slug of its file name without the folders and the extension; the kind for a name
     * without letters or digits.
     */
    public static function fromFilename(string $filename, MediaKind $kind): self
    {
        $base = pathinfo(basename(str_replace('\\', '/', $filename)), PATHINFO_FILENAME);

        return self::tryFrom($base) ?? new self($kind->value);
    }

    /**
     * The name an editor typed, as a slug; null when nothing of it is left. A dot or a slash separates words like a
     * space, so "teamfoto.v2" becomes "teamfoto-v2".
     */
    public static function tryFrom(string $typed): ?self
    {
        $slug = Str::slug((string) preg_replace('#[./\\\\]+#', ' ', $typed));
        $slug = rtrim(Str::substr($slug, 0, self::MAX_LENGTH), '-');

        return $slug === '' ? null : new self($slug);
    }
}
