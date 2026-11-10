<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Illuminate\Http\UploadedFile;

/**
 * The upload field of the CMS: one field for the media library, the picker and the edit shell.
 */
final class UploadField
{
    /**
     * The largest upload php itself takes (upload_max_filesize and post_max_size), as an editor reads it: "2 MB".
     * Above it a file never reaches the library, whatever media.max_upload_kb says.
     */
    public static function serverLimit(): string
    {
        $kilobytes = intdiv((int) UploadedFile::getMaxFilesize(), 1024);

        return $kilobytes % 1024 === 0 ? intdiv($kilobytes, 1024).' MB' : $kilobytes.' kB';
    }
}
