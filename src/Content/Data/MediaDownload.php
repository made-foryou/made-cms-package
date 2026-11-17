<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * A file of the media library to save (OpenMediaDownload): its bytes as a stream to read once, the name to save it
 * under, its type and its size in bytes.
 */
final readonly class MediaDownload
{
    /**
     * @param  resource  $stream  the bytes of the file, read once and closed by whoever sends them
     * @param  string  $filename  {name}.{extension}: the slug of the file with the extension of what it is
     */
    public function __construct(
        public mixed $stream,
        public string $filename,
        public string $mime,
        public int $size,
    ) {}
}
