<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Made\CmsPackage\Content\Data\DraftChanges;
use Made\CmsPackage\Support\Locale;

/**
 * What the bar of the edit shell shows about the draft of a page, and the answer to publishing or discarding it.
 */
final class DraftResponse
{
    public static function state(DraftChanges $changes): JsonResponse
    {
        $response = new JsonResponse(['draft' => self::data($changes)]);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * Publishing and discarding answer nothing: the bar asks for the state afterwards.
     */
    public static function done(): Response
    {
        $response = new Response('', Response::HTTP_NO_CONTENT);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * The state with its texts, so the script of the bar never builds a plural itself.
     *
     * @return array{live: bool, changes: int, publishable: bool, discardable: bool, label: string, discard_text: string}
     */
    public static function data(DraftChanges $changes): array
    {
        $locale = Locale::launcher();
        $count = $changes->count();
        $t = static fn (string $key): string => (string) __('made-cms-package::cms.edit.'.$key, [], $locale);
        $choice = static fn (string $key): string => trans_choice('made-cms-package::cms.edit.'.$key, $count, [], $locale);

        return [
            'live' => $changes->live,
            'changes' => $count,
            'publishable' => $changes->publishable,
            'discardable' => $changes->discardable,
            'label' => $count === 0 && ! $changes->live ? $t('unpublished') : $choice('changes'),
            'discard_text' => $changes->live ? $choice('discard_text').' '.$t('discard_live') : $choice('discard_text'),
        ];
    }
}
