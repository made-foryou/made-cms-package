<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\MediaKind;

/**
 * What the check of an upload read from its contents: a file the library takes.
 */
final readonly class CheckedMedia
{
    /**
     * @param  string  $mime  the type of the contents, never of the name
     * @param  string  $extension  the extension that goes with the type, such as jpg
     * @param  int  $size  in bytes
     */
    public function __construct(
        public string $mime,
        public MediaKind $kind,
        public string $extension,
        public int $size,
    ) {}
}
