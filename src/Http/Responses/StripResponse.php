<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Data\PageTree;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Data\StripDescription;
use Made\CmsPackage\Content\Enums\MediaKind;

/**
 * A strip for the side panel of the edit shell, as JSON. The shell stays loaded, so the panel talks to the server
 * without reloading it; the answer is never stored by a cache.
 */
final class StripResponse
{
    /**
     * The fields and values of a strip, the images its fields hold and the pages of the site a link can go to (in the
     * order of the menu). The values stay in their stored form (an image as the id of its file), so the panel sends
     * them back as they are; `media` lists the files by id with what the panel shows and what the site renders.
     */
    public static function describe(StripDescription $strip, PageTree $pages): JsonResponse
    {
        $images = array_filter($strip->media->files, static fn (MediaFile $file): bool => $file->kind === MediaKind::Image);

        return self::json([
            'strip' => [...$strip->toArray(), 'media' => array_values(array_map(self::image(...), $images))],
            'pages' => array_map(static fn (PageNode $page): array => [
                'id' => $page->id,
                'title' => $page->title,
                'path' => $page->path?->value,
                'status' => $page->status->value,
            ], $pages->flatten()),
        ]);
    }

    /**
     * The strip as it is stored now: new items carry the id the server gave them.
     */
    public static function saved(Strip $strip): JsonResponse
    {
        return self::json([
            'strip' => [
                'id' => $strip->id,
                'visible' => $strip->visible,
                'values' => $strip->values,
            ],
        ]);
    }

    /**
     * An image as the panel shows it: its name, "1600 × 1067 · 412 kB", its folder and alt text, and as the site
     * renders it, for watching along. Never its place on the disk.
     *
     * @return array{id: int, url: string, name: string, meta: string, folder: string|null, alt: string|null, image: array<string, mixed>}
     */
    private static function image(MediaFile $file): array
    {
        $meta = $file->width !== null && $file->height !== null
            ? [(string) __('made-cms-package::media.details.dimensions', ['width' => $file->width, 'height' => $file->height])]
            : [];

        return [
            'id' => $file->id,
            'url' => $file->url,
            'name' => $file->name,
            'meta' => implode(' · ', [...$meta, self::size($file->size)]),
            'folder' => $file->folder,
            'alt' => $file->alt,
            'image' => $file->image(),
        ];
    }

    /**
     * The size of a file in kB (1000 bytes, at least 1), from 1 MB on in MB with one decimal.
     */
    private static function size(int $bytes): string
    {
        if ($bytes < 1_000_000) {
            return (string) __('made-cms-package::media.details.kb', ['size' => max(1, (int) round($bytes / 1000))]);
        }

        $decimal = (string) __('made-cms-package::media.details.decimal');

        return (string) __('made-cms-package::media.details.mb', ['size' => number_format($bytes / 1_000_000, 1, $decimal, '')]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function json(array $data): JsonResponse
    {
        $response = new JsonResponse($data);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
