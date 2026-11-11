<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Content\Enums\MediaKind;

/**
 * A file of the media library as the CMS shows it, the same in the side panel and in the upload field: its name, the
 * extension of the file a visitor gets ("webp" for a photo that was scaled down), "1600 × 1067 · 412 kB", its folder
 * and alt text, and as the site renders it. Never its place on the disk.
 *
 * @phpstan-import-type RenderedImage from MediaFile
 *
 * @phpstan-type Details array{id: int, url: string, name: string, extension: string, meta: string, folder: string|null, alt: string|null, image: RenderedImage|null}
 */
final class MediaDetails
{
    /**
     * @return Details `image` is the file as the site renders it, for watching along; null for a document or a video
     */
    public static function of(MediaFile $file): array
    {
        $meta = $file->width !== null && $file->height !== null
            ? [(string) __('made-cms-package::media.details.dimensions', ['width' => $file->width, 'height' => $file->height])]
            : [];

        return [
            'id' => $file->id,
            'url' => $file->url,
            'name' => $file->name,
            'extension' => self::extension($file->url),
            'meta' => implode(' · ', [...$meta, self::size($file->size)]),
            'folder' => $file->folder,
            'alt' => $file->alt,
            'image' => $file->kind === MediaKind::Image ? $file->image() : null,
        ];
    }

    /**
     * The extension of the file a visitor opens, in small letters: the type of what the site serves.
     */
    private static function extension(string $url): string
    {
        return strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    }

    /**
     * The size of a file in kB (1000 bytes, at least 1), from 1 MB on in MB with one decimal.
     */
    public static function size(int $bytes): string
    {
        if ($bytes < 1_000_000) {
            return (string) __('made-cms-package::media.details.kb', ['size' => max(1, (int) round($bytes / 1000))]);
        }

        $decimal = (string) __('made-cms-package::media.details.decimal');

        return (string) __('made-cms-package::media.details.mb', ['size' => number_format($bytes / 1_000_000, 1, $decimal, '')]);
    }
}
