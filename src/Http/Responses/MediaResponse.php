<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Made\CmsPackage\Content\Data\DeletedMedia;
use Made\CmsPackage\Content\Data\MediaCrop;
use Made\CmsPackage\Content\Data\MediaDescription;
use Made\CmsPackage\Content\Data\MediaDownload;
use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Content\Data\MediaFiles;
use Made\CmsPackage\Content\Data\MediaFilter;
use Made\CmsPackage\Content\Data\MediaFolders;
use Made\CmsPackage\Content\Data\MediaHistory;
use Made\CmsPackage\Content\Data\MediaList;
use Made\CmsPackage\Content\Data\MediaPlace;
use Made\CmsPackage\Content\Data\MediaPlaces;
use Made\CmsPackage\Content\Data\ScaledImage;
use Made\CmsPackage\Content\Enums\UsageVersion;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Http\Cms\MediaDetails;
use Made\CmsPackage\Http\Cms\PageLinks;
use Made\CmsPackage\Support\Locale;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * The file as it is stored now, after a new version took its place, with what to tell the editor: the name of the
     * new version, and that the site shows it when a published page that is not in the bin holds the file.
     */
    public static function replaced(MediaFile $file, MediaPlaces $places): JsonResponse
    {
        $details = self::file($file);
        $live = array_filter($places->places, static fn (MediaPlace $place): bool => ! $place->pageDeleted && $place->in(UsageVersion::Published)) !== [];

        return self::json([
            'file' => $details,
            'notice' => (string) __('made-cms-package::media.library.new_version.'.($live ? 'done_live' : 'done'), ['name' => $details['file']], Locale::launcher()),
        ]);
    }

    /**
     * The file as it is stored now, after the version that waited was put back, with what to tell the editor: the
     * version the site shows now and the one that waits in its turn.
     */
    public static function restored(MediaFile $file, MediaHistory $history): JsonResponse
    {
        return self::json([
            'file' => self::file($file),
            'notice' => $history->previous === null ? null : (string) __('made-cms-package::media.library.restored', [
                'version' => $history->version,
                'waiting' => $history->previous->version,
            ], Locale::launcher()),
        ]);
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
     * Each file says whether a page uses it (`used`, so it cannot be deleted) and has the link that keeps leading to
     * it (`link`, absolute, for the clipboard).
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
            'files' => array_map(static fn (MediaFile $file): array => [
                ...self::file($file),
                'link' => self::link($file),
                'used' => in_array($file->id, $list->used, true),
            ], $list->files),
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
     *
     * Next to them the line of its history ("Geüpload door Menno Tempelaar op 9 oktober 2026 · versie 2 · vervangen
     * door …") with the version that waits to be put back and the name of the button that puts it back, whether it can
     * be deleted with the reason in words, and what replacing it changes ("Staat op 2 pagina's; vervangen verandert hem
     * daar meteen, ook op de site."; null when no page that is not in the bin holds it).
     *
     * @param  array<int, string>  $names  the names of the editors of its history, by id (NameEditors)
     */
    public static function described(MediaDescription $description, array $names = []): JsonResponse
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
            'file' => [...self::file($description->file), 'link' => self::link($description->file), 'used' => ! $description->places->isEmpty()],
            'history' => self::history($description->history, $names, $locale),
            'removal' => self::removal($description->places, $locale),
            'replace' => ['warning' => $count === 0 ? null : trans_choice('made-cms-package::media.library.new_version.warning.'.$description->file->kind->value, $count, ['count' => $count], $locale)],
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
     * What deleting files did: the ids it deleted, the ids it kept because a page uses them and the ids that were
     * gone already, with what to tell the editor in words ("3 bestanden verwijderd.", "teamfoto.webp en logo.svg
     * staan nog op een pagina en blijven staan."), null when there is nothing to tell.
     */
    public static function deleted(DeletedMedia $result): JsonResponse
    {
        $locale = Locale::launcher();
        $ids = static fn (array $files): array => array_map(static fn (Media $media): int => $media->id, $files);
        $file = static fn (Media $media): string => basename($media->path);
        $deleted = count($result->deleted);
        $kept = count($result->kept);

        return self::json([
            'deleted' => $ids($result->deleted),
            'kept' => $ids($result->kept),
            'missing' => $result->missing,
            'notice' => $deleted === 0 ? null : trans_choice('made-cms-package::media.library.removed.deleted', $deleted, [
                'name' => $file($result->deleted[0]),
                'count' => $deleted,
            ], $locale),
            'refusal' => $kept === 0 ? null : trans_choice('made-cms-package::media.library.removed.kept', $kept, [
                'names' => self::names(array_map($file, $result->kept), $locale),
            ], $locale),
        ]);
    }

    /**
     * A file to save, read through the app from the disk: its bytes with the name it is saved under. Never stored by
     * a cache, because of a photo it is the private original.
     */
    public static function download(MediaDownload $download): StreamedResponse
    {
        $stream = $download->stream;

        return new StreamedResponse(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, Response::HTTP_OK, [
            'Content-Type' => $download->mime,
            'Content-Length' => (string) $download->size,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $download->filename),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The lasting link of a file sends the visitor to the address it has now; never kept, because the next version,
     * crop or name has another one.
     */
    public static function linked(string $url): RedirectResponse
    {
        return new RedirectResponse($url, Response::HTTP_FOUND, ['Cache-Control' => 'no-store']);
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

    /**
     * The link that keeps leading to a file, absolute because it goes to the clipboard; null for a file without a
     * folder of its own.
     */
    private static function link(MediaFile $file): ?string
    {
        return $file->key === null
            ? null
            : route('made-cms-package.media.link', ['ulid' => $file->key, 'name' => $file->name]);
    }

    /**
     * The line of the history of a file and the version that waits, in words.
     *
     * @param  array<int, string>  $names
     * @return array{line: string, previous: string|null, restore: string|null, can_restore: bool}
     */
    private static function history(MediaHistory $history, array $names, string $locale): array
    {
        $date = static fn (CarbonImmutable $at): string => $at->settings(['locale' => $locale])->isoFormat('D MMMM YYYY');
        $text = static fn (string $key, array $replace = []): string => (string) __('made-cms-package::media.library.history.'.$key, $replace, $locale);
        $line = [];

        if ($history->uploadedAt !== null) {
            $uploader = $history->uploadedBy === null ? null : ($names[$history->uploadedBy] ?? null);
            $line[] = $uploader === null
                ? $text('uploaded', ['date' => $date($history->uploadedAt)])
                : $text('uploaded_by', ['name' => $uploader, 'date' => $date($history->uploadedAt)]);
        }

        $line[] = $text('version', ['version' => $history->version]);

        if ($history->replacedAt !== null) {
            $replacer = $history->replacedBy === null ? null : ($names[$history->replacedBy] ?? null);
            $line[] = $replacer === null
                ? $text('replaced', ['date' => $date($history->replacedAt)])
                : $text('replaced_by', ['name' => $replacer, 'date' => $date($history->replacedAt)]);
        }

        return [
            'line' => Str::ucfirst(implode(' · ', $line)),
            'previous' => $history->previous === null ? null : $text('previous', ['version' => $history->previous->version]),
            'restore' => $history->previous === null ? null : $text('restore', ['version' => $history->previous->version]),
            'can_restore' => $history->previous !== null,
        ];
    }

    /**
     * Whether a file can be deleted, with the reason in words: a file a page uses stays, also on a page in the bin
     * until it is pruned.
     *
     * @return array{allowed: bool, note: string}
     */
    private static function removal(MediaPlaces $places, string $locale): array
    {
        $shown = $places->pageCount(withDeleted: false);
        $all = $places->pageCount();

        return match (true) {
            $places->isEmpty() => ['allowed' => true, 'note' => (string) __('made-cms-package::media.library.removal.free', [], $locale)],
            $shown > 0 => ['allowed' => false, 'note' => trans_choice('made-cms-package::media.library.removal.used', $shown, ['count' => $shown], $locale)],
            default => ['allowed' => false, 'note' => trans_choice('made-cms-package::media.library.removal.bin', $all, ['count' => $all], $locale)],
        };
    }

    /**
     * Names as one list in words: "a", "a en b", "a, b en c".
     *
     * @param  list<string>  $names
     */
    private static function names(array $names, string $locale): string
    {
        $last = array_pop($names);

        return $names === [] ? (string) $last : implode(', ', $names).' '.__('made-cms-package::media.and', [], $locale).' '.$last;
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
