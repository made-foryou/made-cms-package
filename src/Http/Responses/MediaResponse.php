<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Data\MediaCrop;
use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Content\Data\MediaFolders;
use Made\CmsPackage\Content\Data\MediaList;
use Made\CmsPackage\Content\Data\ScaledImage;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Http\Cms\MediaDetails;
use Made\CmsPackage\Support\Locale;
use Symfony\Component\HttpFoundation\Response;

/**
 * The media library for the upload field, the picker and the crop step, as JSON: a file, a page of files, the folders
 * or a photo to crop, and the original a crop is drawn on. The page
 * around them stays loaded, so they talk to the server without reloading it; an answer is never stored by a cache.
 */
final class MediaResponse
{
    /**
     * The file an upload became: a photo as the WebP it was scaled down to.
     */
    public static function uploaded(MediaFile $file): JsonResponse
    {
        return self::json(['file' => self::file($file)], Response::HTTP_CREATED);
    }

    /**
     * The file as it is stored now, after its alt text changed.
     */
    public static function saved(MediaFile $file): JsonResponse
    {
        return self::json(['file' => self::file($file)]);
    }

    /**
     * One page of a list, each file as the upload field gets it, with the number of files the list holds in words
     * ("12 afbeeldingen"), so the script never builds a plural itself.
     *
     * @param  MediaKind|null  $kind  the kind the list shows, which names its files; null for every kind
     */
    public static function listed(MediaList $list, ?MediaKind $kind): JsonResponse
    {
        return self::json([
            'files' => array_map(self::file(...), $list->files),
            'page' => $list->page,
            'total' => $list->total,
            'more' => $list->more,
            'summary' => trans_choice('made-cms-package::media.browser.count.'.($kind->value ?? 'all'), $list->total, ['count' => $list->total], Locale::launcher()),
        ]);
    }

    /**
     * The folders from a to z with the number of their files, and the number of all files.
     */
    public static function folders(MediaFolders $folders): JsonResponse
    {
        return self::json($folders->toArray());
    }

    /**
     * The folder an editor chose is remembered; there is nothing to answer.
     */
    public static function remembered(): Response
    {
        $response = new Response('', Response::HTTP_NO_CONTENT);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * A photo as the crop step shows it: the file, its original (the address of its preview, its upright size, bytes
     * and extension), how it was cut from it, and the line under the title ("teamfoto.jpg · 4000 × 2667 · 4,2 MB ·
     * geüpload 9 oktober 2026") with the pages a crop changes ("Staat op 2 pagina's …"; null for none), in words,
     * so the script never builds a plural or a date itself. Never the place of a file on the disk.
     */
    public static function crop(MediaCrop $crop): JsonResponse
    {
        $locale = Locale::launcher();
        $line = [
            "{$crop->file->name}.{$crop->originalExtension}",
            (string) __('made-cms-package::media.details.dimensions', ['width' => $crop->originalWidth, 'height' => $crop->originalHeight], $locale),
        ];

        if ($crop->originalSize !== null) {
            $line[] = MediaDetails::size($crop->originalSize);
        }

        if ($crop->uploadedAt !== null) {
            $line[] = (string) __('made-cms-package::media.crop.uploaded', ['date' => $crop->uploadedAt->settings(['locale' => $locale])->isoFormat('D MMMM YYYY')], $locale);
        }

        return self::json([
            'file' => self::file($crop->file),
            'folder' => $crop->folderId,
            'original' => [
                'url' => request()->getBaseUrl().route('made-cms-package.media.original', ['media' => $crop->file->id], false),
                'width' => $crop->originalWidth,
                'height' => $crop->originalHeight,
                'size' => $crop->originalSize,
                'extension' => $crop->originalExtension,
            ],
            'crop' => $crop->area->toArray(),
            'scale_down' => $crop->scaleDown,
            'pages' => $crop->pages,
            'line' => implode(' · ', $line),
            'used' => $crop->pages === 0 ? null : trans_choice('made-cms-package::media.crop.used', $crop->pages, ['count' => $crop->pages], $locale),
        ]);
    }

    /**
     * The whole original of a photo for the crop step, as the image itself; never stored by a cache, because it shows
     * what a crop left out.
     */
    public static function original(ScaledImage $preview): Response
    {
        $contents = (string) $preview->contents;

        return new Response($contents, Response::HTTP_OK, [
            'Content-Type' => $preview->mime,
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The file as the CMS shows it (MediaDetails), with its kind, the name of the file a visitor opens and its size
     * in bytes, so the field can say how much smaller a photo became. Never its place on the disk or its original.
     *
     * @return array<string, mixed>
     */
    private static function file(MediaFile $file): array
    {
        return [
            ...MediaDetails::of($file),
            'kind' => $file->kind->value,
            'file' => basename((string) parse_url($file->url, PHP_URL_PATH)),
            'size' => $file->size,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function json(array $data, int $status = Response::HTTP_OK): JsonResponse
    {
        $response = new JsonResponse($data, $status);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
