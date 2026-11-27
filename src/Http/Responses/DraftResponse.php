<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Made\CmsPackage\Content\Data\DraftChanges;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Cms\Day;
use Made\CmsPackage\Http\Cms\EditExits;
use Made\CmsPackage\Support\Locale;

/**
 * What the bar of the edit shell shows about the draft of a page, and the answer to publishing or discarding it.
 */
final class DraftResponse
{
    public static function state(DraftChanges $changes, Page $page): JsonResponse
    {
        $response = new JsonResponse(['draft' => self::data($changes, $page)]);
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
     * The state with its texts, so the script of the bar never builds a plural itself, and the addresses it leaves for:
     * they change when the page is published, so every answer holds them. A planned publication takes the place of
     * "Publiceren" ("Gepland · 14 nov 09:00"); null without one.
     *
     * @return array{live: bool, changes: int, publishable: bool, discardable: bool, label: string, discard_text: string, short_label: string, stop_title: string, stop_text: string, exits: array{stop: string, publish: string}, scheduled: array{label: string, short_label: string, text: string}|null}
     */
    public static function data(DraftChanges $changes, Page $page): array
    {
        $locale = Locale::launcher();
        $count = $changes->count();
        $t = static fn (string $key): string => (string) __('made-cms-package::cms.edit.'.$key, [], $locale);
        $choice = static fn (string $key): string => trans_choice('made-cms-package::cms.edit.'.$key, $count, [], $locale);
        $exits = EditExits::of($page);
        // What stopping with a draft tells: visitors see it only after publishing, and where the editor lands.
        $stopText = $changes->live ? $choice('stop_text') : $t('stop_text_new');

        $discardText = $changes->live ? $choice('discard_text').' '.$t('discard_live') : $choice('discard_text');

        return [
            'live' => $changes->live,
            'changes' => $count,
            'publishable' => $changes->publishable,
            'discardable' => $changes->discardable,
            'label' => $count === 0 && ! $changes->live ? $t('unpublished') : $choice('changes'),
            'discard_text' => $page->publish_at === null ? $discardText : $discardText.' '.$t('discard_scheduled'),
            // On a narrow bar the label drops the word "Concept".
            'short_label' => $count === 0 && ! $changes->live ? $t('unpublished_short') : $choice('changes_short'),
            'stop_title' => $changes->live ? $t('stop_title') : $t('stop_title_new'),
            'stop_text' => $exits->stopsAtPages ? $stopText.' '.$t('stop_to_pages') : $stopText,
            'exits' => $exits->toArray(),
            'scheduled' => $page->publish_at === null ? null : self::scheduled($page->publish_at, $locale),
        ];
    }

    /**
     * @return array{label: string, short_label: string, text: string}
     */
    private static function scheduled(CarbonImmutable $moment, string $locale): array
    {
        $text = static fn (string $key, array $replace = []): string => (string) __('made-cms-package::cms.edit.'.$key, $replace, $locale);
        $short = Day::shortMoment($moment, $locale);

        return [
            'label' => $text('scheduled', ['moment' => $short]),
            'short_label' => $short,
            'text' => $text($moment->lessThanOrEqualTo(now()) ? 'scheduled_late' : 'scheduled_text', ['at' => Day::at($moment, $locale)]),
        ];
    }
}
