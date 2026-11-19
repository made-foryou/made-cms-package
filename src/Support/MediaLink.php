<?php

declare(strict_types=1);

namespace Made\CmsPackage\Support;

/**
 * The lasting link of a file of the media library: it keeps leading to the file after a new version, a crop or a new
 * name, because it only redirects to the address the file has now. Built from the configured url, so it works on a
 * site in a subfolder and a forged Host header never ends up in a page a cache keeps.
 */
final class MediaLink
{
    /**
     * @param  string  $key  the folder of the file on the disk (media/{ulid})
     * @param  string  $name  the name of the file, only there to read
     */
    public static function url(string $key, string $name): string
    {
        return Site::url(route('made-cms-package.media.link', ['ulid' => $key, 'name' => $name], absolute: false));
    }
}
