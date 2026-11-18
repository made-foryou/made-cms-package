<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Content\Enums\MediaKind;

/**
 * A file of the media library as the CMS shows it, the same in the side panel and in the upload field: its name, the
 * extension of the file a visitor gets ("webp" for a photo that was scaled down), "1600 × 1067 · 412 kB", its folder
 * by name and id, its alt text, the url to show it small at (a tile, a row of the upload field) and as the site renders
 * it. Never its place on the disk.
 *
 * @phpstan-import-type RenderedImage from MediaFile
 *
 * @phpstan-type Details array{id: int, url: string, thumb: string|null, name: string, extension: string, meta: string, folder: string|null, folder_id: int|null, alt: string|null, image: RenderedImage|null, croppable: bool}
 */
final class MediaDetails
{
    /**
     * @return Details `image` is the file as the site renders it, for watching along; null for a document or a video;
     *                 `croppable` whether the editor can cut it from its original again
     */
    public static function of(MediaFile $file): array
    {
        $meta = $file->width !== null && $file->height !== null
            ? [(string) __('made-cms-package::media.details.dimensions', ['width' => $file->width, 'height' => $file->height])]
            : [];

        return [
            'id' => $file->id,
            'url' => $file->url,
            'thumb' => $file->thumb,
            'name' => $file->name,
            'extension' => $file->extension(),
            'meta' => implode(' · ', [...$meta, self::size($file->size)]),
            'folder' => $file->folder,
            'folder_id' => $file->folderId,
            'alt' => $file->alt,
            'image' => $file->kind === MediaKind::Image ? $file->image() : null,
            'croppable' => $file->croppable,
        ];
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
