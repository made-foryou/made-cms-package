<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Data\EditableStrip;
use Made\CmsPackage\Content\Data\EditableStrips;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Support\Locale;
use Symfony\Component\HttpFoundation\Response;

/**
 * The strips of a page for the opbouw of the edit shell, as JSON: as they are, or after a strip was added, moved,
 * removed or put back.
 * Every answer holds the whole list in its stored order, so the place of a strip in it is the position the endpoints
 * take, and the counts as a text, so the script never builds a plural itself. The answer is never stored by a cache.
 */
final class PageStripsResponse
{
    public static function listed(EditableStrips $strips): JsonResponse
    {
        return self::json($strips);
    }

    public static function added(EditableStrips $strips, Strip $strip): JsonResponse
    {
        return self::json($strips, ['strip' => ['id' => $strip->id]], Response::HTTP_CREATED);
    }

    public static function moved(EditableStrips $strips): JsonResponse
    {
        return self::json($strips);
    }

    /**
     * The removed strip comes along as it was stored: putting it back sends it as it is.
     */
    public static function removed(EditableStrips $strips, Strip $removed): JsonResponse
    {
        return self::json($strips, ['removed' => $removed->toArray()]);
    }

    public static function restored(EditableStrips $strips, Strip $strip): JsonResponse
    {
        return self::json($strips, ['strip' => ['id' => $strip->id]], Response::HTTP_CREATED);
    }

    /**
     * Every strip of the working copy with the texts the edit shell shows of it.
     *
     * @return list<array{id: string, type: string, label: string, visible: bool, known: bool, fields: string, summary: string|null}>
     */
    public static function strips(EditableStrips $strips): array
    {
        $locale = Locale::launcher();

        return array_map(static fn (EditableStrip $strip): array => [
            'id' => $strip->id,
            'type' => $strip->type,
            'label' => $strip->label,
            'visible' => $strip->visible,
            'known' => $strip->known,
            'fields' => trans_choice('made-cms-package::cms.edit.fields', $strip->fields, [], $locale),
            'summary' => $strip->summary,
        ], $strips->strips);
    }

    /**
     * "5 stroken, 1 verborgen, 1 niet op de site", or that there are none yet.
     */
    public static function counts(EditableStrips $strips): string
    {
        $locale = Locale::launcher();
        $hidden = count(array_filter($strips->strips, static fn (EditableStrip $strip): bool => $strip->known && ! $strip->visible));
        $unknown = count(array_filter($strips->strips, static fn (EditableStrip $strip): bool => ! $strip->known));
        $count = static fn (string $key, int $number): string => trans_choice('made-cms-package::cms.edit.counts.'.$key, $number, [], $locale);

        return implode(', ', array_filter([
            $count('strips', count($strips->strips)),
            $hidden > 0 ? $count('hidden', $hidden) : null,
            $unknown > 0 ? $count('unknown', $unknown) : null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function json(EditableStrips $strips, array $data = [], int $status = Response::HTTP_OK): JsonResponse
    {
        $response = new JsonResponse([...$data, 'strips' => self::strips($strips), 'counts' => self::counts($strips)], $status);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
