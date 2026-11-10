<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Http\Cms\MediaDetails;
use Symfony\Component\HttpFoundation\Response;

/**
 * A file of the media library for the upload field, as JSON. The page around the field stays loaded, so the field
 * talks to the server without reloading it; the answer is never stored by a cache.
 */
final class MediaResponse
{
    /**
     * The file an upload became: a photo as the WebP it was scaled down to.
     */
    public static function uploaded(MediaFile $file): JsonResponse
    {
        return self::json($file, Response::HTTP_CREATED);
    }

    /**
     * The file as it is stored now, after its alt text changed.
     */
    public static function saved(MediaFile $file): JsonResponse
    {
        return self::json($file, Response::HTTP_OK);
    }

    /**
     * The file as the CMS shows it (MediaDetails), with its kind, the name of the file a visitor opens and its size
     * in bytes, so the field can say how much smaller a photo became. Never its place on the disk or its original.
     */
    private static function json(MediaFile $file, int $status): JsonResponse
    {
        $response = new JsonResponse([
            'file' => [
                ...MediaDetails::of($file),
                'kind' => $file->kind->value,
                'file' => basename((string) parse_url($file->url, PHP_URL_PATH)),
                'size' => $file->size,
            ],
        ], $status);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
