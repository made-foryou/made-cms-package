<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\MediaKind;

/**
 * What the media library takes, as the site configured it: the one source for the check of an upload and for what
 * the upload field tells an editor before they choose a file.
 */
final readonly class UploadLimits
{
    /**
     * @param  int  $maxBytes  the largest file the library takes
     * @param  string  $max  the same as an editor reads it, such as "25 MB"
     * @param  array<string, string>  $extensions  the types the library takes with the extension of each, such as image/jpeg => jpg
     * @param  string  $types  the types as an editor reads them, such as "jpg, png of svg"
     * @param  list<MediaKind>  $kinds  the kinds of file among the types, each once
     */
    public function __construct(
        public int $maxBytes,
        public string $max,
        public array $extensions,
        public string $types,
        public array $kinds,
    ) {}

    /**
     * The extension a file of this type gets, or null when the library does not take the type.
     */
    public function extension(string $mime): ?string
    {
        return $this->extensions[$mime] ?? null;
    }

    /**
     * @return list<string>
     */
    public function mimes(): array
    {
        return array_keys($this->extensions);
    }
}
