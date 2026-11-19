<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Data\PageTree;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Data\StripDescription;
use Made\CmsPackage\Http\Cms\MediaDetails;

/**
 * A strip for the side panel of the edit shell, as JSON. The shell stays loaded, so the panel talks to the server
 * without reloading it; the answer is never stored by a cache.
 */
final class StripResponse
{
    /**
     * The fields and values of a strip, the files its fields hold (images, documents and videos) and the pages of the site a link can go to (in the
     * order of the menu). The values stay in their stored form (a file as its id), so the panel sends
     * them back as they are; `media` lists the files by id with what the panel shows and what the site renders
     * (MediaDetails).
     */
    public static function describe(StripDescription $strip, PageTree $pages): JsonResponse
    {
        return self::json([
            'strip' => [...$strip->toArray(), 'media' => array_values(array_map(MediaDetails::of(...), $strip->media->files))],
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
     * @param  array<string, mixed>  $data
     */
    private static function json(array $data): JsonResponse
    {
        $response = new JsonResponse($data);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
