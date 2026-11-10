<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * A file to add to the media library. It holds no type of the HTTP layer: the upload endpoint passes the temporary
 * file of the request, the import of the scan a file it fetched.
 */
final readonly class UploadedMedia
{
    /**
     * @param  string  $path  a readable file on the local disk of the server
     * @param  string  $filename  the name of the file as the editor had it, for the name in the library
     * @param  int|null  $folderId  the folder to put it in; null for none
     * @param  int|null  $uploadedBy  the id of the editor who uploads it; null for none, such as an import
     */
    public function __construct(
        public string $path,
        public string $filename,
        public ?int $folderId = null,
        public ?int $uploadedBy = null,
    ) {}
}
