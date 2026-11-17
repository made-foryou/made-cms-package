<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * The files a file of the media library holds on its disk, and how each of them is written. The package says it with
 * every write, so the visibility of the disk itself never decides:
 *
 * - the original is private: it holds the full size and the metadata of the camera (GPS too), and only the app reads it,
 *   to crop the photo again;
 * - the photo, its thumbnail and a file kept as it came in are public, for the visitor and the tiles of the CMS;
 * - the photo and its thumbnail may be kept by a browser or CDN for a year without asking again: every crop and every
 *   new name writes them at a new address. A file kept as it came in stays at its address when it is replaced (#57),
 *   so it gets no such header.
 *
 * The folders are public as well, so a web server that runs as another user can read the public files of a local
 * disk that is private itself. A local disk turns the visibility into permissions (0600 or 0644) and ignores the
 * header; an S3 disk such as DigitalOcean Spaces turns it into the ACL of the object and sends the header along.
 */
enum MediaFileRole: string
{
    /** The photo the site shows, scaled down from the original. */
    case Photo = 'photo';

    /** The photo small, for the tiles of the CMS. */
    case Thumbnail = 'thumbnail';

    /** The photo as it was uploaded, which a crop starts from. */
    case Original = 'original';

    /** An svg, an animated gif, a document or a video, stored exactly as it came in. */
    case AsUploaded = 'as_uploaded';

    /** A year, and never asked again: the file at an address never changes. */
    public const string CACHE_CONTROL = 'public, max-age=31536000, immutable';

    /**
     * The options a write of this file hands the disk.
     *
     * @return array{visibility: string, directory_visibility: string, CacheControl?: string}
     */
    public function writeOptions(): array
    {
        $public = ['visibility' => Filesystem::VISIBILITY_PUBLIC, 'directory_visibility' => Filesystem::VISIBILITY_PUBLIC];

        return match ($this) {
            self::Photo, self::Thumbnail => [...$public, 'CacheControl' => self::CACHE_CONTROL],
            self::Original => [...$public, 'visibility' => Filesystem::VISIBILITY_PRIVATE],
            self::AsUploaded => $public,
        };
    }
}
