<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Data\MediaCrop;
use Made\CmsPackage\Content\Data\MediaDescription;
use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Content\Data\MediaFiles;
use Made\CmsPackage\Content\Data\MediaFilter;
use Made\CmsPackage\Content\Data\MediaFolders;
use Made\CmsPackage\Content\Data\MediaList;
use Made\CmsPackage\Content\Data\MediaPlace;
use Made\CmsPackage\Content\Data\ScaledImage;
use Made\CmsPackage\Content\Enums\UsageVersion;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Http\Cms\MediaDetails;
use Made\CmsPackage\Http\Cms\PageLinks;
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
     * ("12 afbeeldingen", "3 afbeeldingen zonder alt-tekst", "2 bestanden voor “team”"), so the script never builds a
     * plural itself.
     */
    public static function listed(MediaList $list, MediaFilter $filter): JsonResponse
    {
        return self::json(self::list($list, $filter));
    }

    /**
     * A page of a list as listed() answers it, also for the first page a screen hands its script.
     *
     * @return array{files: list<array<string, mixed>>, page: int, total: int, more: bool, summary: string}
     */
    public static function list(MediaList $list, MediaFilter $filter): array
    {
        $locale = Locale::launcher();
        $count = $filter->withoutAlt ? 'without_alt' : ($filter->kind->value ?? 'all');
        $summary = trans_choice('made-cms-package::media.browser.count.'.$count, $list->total, ['count' => $list->total], $locale);
        $search = trim($filter->search);

        return [
            'files' => array_map(self::file(...), $list->files),
            'page' => $list->page,
            'total' => $list->total,
            'more' => $list->more,
            'summary' => $search === '' ? $summary : (string) __('made-cms-package::media.browser.found', ['count' => $summary, 'search' => $search], $locale),
        ];
    }

    /**
     * The folders from a to z with the number of their files, the number of all files, and the numbers of the whole
     * library per kind and of the images without an alt text.
     */
    public static function folders(MediaFolders $folders): JsonResponse
    {
        return self::json($folders->toArray());
    }

    /**
     * The folder a name was given, as the folders of the list have it.
     */
    public static function folder(MediaFolder $folder, int $status = Response::HTTP_OK): JsonResponse
    {
        return self::json(['folder' => ['id' => $folder->id, 'name' => $folder->name]], $status);
    }

    /**
     * The folder that was added to the library.
     */
    public static function folderCreated(MediaFolder $folder): JsonResponse
    {
        return self::folder($folder, Response::HTTP_CREATED);
    }

    /**
     * The folder is gone; there is nothing to answer.
     */
    public static function folderDeleted(): Response
    {
        return self::nothing();
    }

    /**
     * The files that were moved, as they are stored now, by id.
     */
    public static function moved(MediaFiles $files): JsonResponse
    {
        $moved = $files->files;
        ksort($moved);

        return self::json(['files' => array_values(array_map(self::file(...), $moved))]);
    }

    /**
     * The folder an editor chose is remembered; there is nothing to answer.
     */
    public static function remembered(): Response
    {
        return self::nothing();
    }

    /**
     * A file as the side column of the media library shows it: the file, the heading of its places in words
     * ("Gebruikt op 2 pagina's"; "Gebruik" when it is on none, a deleted page does not count), and the pages with the
     * places on them. Each page has the address of its edit shell (null for a deleted page, which has none) and a note
     * when it is in the bin or all its places are in one version only ("Alleen in het concept", "Nog op de site, niet
     * meer in het concept"); when its places differ, each place in one version has that note itself.
     */
    public static function described(MediaDescription $description): JsonResponse
    {
        $locale = Locale::launcher();
        $count = $description->places->pageCount(withDeleted: false);
        $pages = [];

        foreach ($description->places->places as $place) {
            $pages[$place->page] ??= [
                'title' => $place->pageTitle,
                'url' => $place->pageDeleted ? null : PageLinks::edit($place->page),
                'deleted' => $place->pageDeleted,
                'notes' => [],
                'places' => [],
            ];
            $pages[$place->page]['notes'][] = self::note($place, $locale);
            $pages[$place->page]['places'][] = ['where' => $place->where, 'note' => self::note($place, $locale)];
        }

        return self::json([
            'file' => self::file($description->file),
            'heading' => $count === 0
                ? (string) __('made-cms-package::media.library.usage', [], $locale)
                : trans_choice('made-cms-package::media.library.used', $count, ['count' => $count], $locale),
            'pages' => array_values(array_map(static function (array $page) use ($locale): array {
                $notes = array_unique($page['notes']);
                $note = match (true) {
                    $page['deleted'] => (string) __('made-cms-package::media.library.deleted', [], $locale),
                    count($notes) === 1 => $notes[0],
                    default => null,
                };

                return [
                    'title' => $page['title'],
                    'url' => $page['url'],
                    'deleted' => $page['deleted'],
                    'note' => $note,
                    'places' => $page['deleted'] || count($notes) === 1
                        ? array_map(static fn (array $place): array => ['where' => $place['where'], 'note' => null], $page['places'])
                        : $page['places'],
                ];
            }, $pages)),
        ]);
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
     * What a place is in when it is in one version of its page only: the draft added it, or the draft took it away
     * while the site still shows it. Null for a place in both, or in the published version of a page without a draft.
     */
    private static function note(MediaPlace $place, string $locale): ?string
    {
        return match (true) {
            ! $place->in(UsageVersion::Published) => (string) __('made-cms-package::media.library.draft_only', [], $locale),
            ! $place->in(UsageVersion::Draft) && $place->pageHasDraft => (string) __('made-cms-package::media.library.removed_in_draft', [], $locale),
            default => null,
        };
    }

    private static function nothing(): Response
    {
        $response = new Response('', Response::HTTP_NO_CONTENT);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
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
