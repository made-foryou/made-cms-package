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
 * - the photo, its narrower sizes, its thumbnail and a file kept as it came in are public, for the visitor and the
 *   tiles of the CMS;
 * - every file a visitor opens may be kept by a browser or CDN for a year without asking again: the bytes at an
 *   address never change. A crop, a new name and a new version (ReplaceMedia, in a folder of its own) write at a new
 *   address, and putting a version back brings back the same bytes at the same address;
 * - a version that waits to be put back is private as a whole (HidePreviousVersion), so a replaced file is gone for a
 *   visitor at once; putting it back makes its files public again by their roles.
 *
 * The folders are public as well, so a web server that runs as another user can read the public files of a local
 * disk that is private itself. A local disk turns the visibility into permissions (0600 or 0644) and ignores the
 * header; an S3 disk such as DigitalOcean Spaces turns it into the ACL of the object and sends the header along.
 */
enum MediaFileRole: string
{
    /** The photo the site shows, scaled down from the original. */
    case Photo = 'photo';

    /** A narrower size of the photo, for the srcset of the site (MakeImageVariants). */
    case Variant = 'variant';

    /** The photo small, for the tiles of the CMS. */
    case Thumbnail = 'thumbnail';

    /** The photo as it was uploaded, which a crop starts from. */
    case Original = 'original';

    /** An svg, an animated gif, a document or a video, stored exactly as it came in. */
    case AsUploaded = 'as_uploaded';

    /** A year, and never asked again: the file at an address never changes. */
    public const string CACHE_CONTROL = 'public, max-age=31536000, immutable';

    /**
     * The role of the file the site shows: the photo of a file with an original, else the file as it came in.
     */
    public static function shown(bool $hasOriginal): self
    {
        return $hasOriginal ? self::Photo : self::AsUploaded;
    }

    /**
     * Who opens this file: the app alone (private) or everyone with its address (public).
     */
    public function visibility(): string
    {
        return match ($this) {
            self::Original => Filesystem::VISIBILITY_PRIVATE,
            self::Photo, self::Variant, self::Thumbnail, self::AsUploaded => Filesystem::VISIBILITY_PUBLIC,
        };
    }

    /**
     * The options a write of this file hands the disk.
     *
     * @return array{visibility: string, directory_visibility: string, CacheControl?: string}
     */
    public function writeOptions(): array
    {
        $options = ['visibility' => $this->visibility(), 'directory_visibility' => Filesystem::VISIBILITY_PUBLIC];

        return match ($this) {
            self::Photo, self::Variant, self::Thumbnail, self::AsUploaded => [...$options, 'CacheControl' => self::CACHE_CONTROL],
            self::Original => $options,
        };
    }
}
