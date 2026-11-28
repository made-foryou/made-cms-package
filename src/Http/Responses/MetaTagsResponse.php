<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\Response;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\MeasureMeta;
use Made\CmsPackage\Content\Data\MetaLength;
use Made\CmsPackage\Content\Data\MetaOverview;
use Made\CmsPackage\Content\Data\MetaPanel;
use Made\CmsPackage\Content\Data\MetaRoundPlace;
use Made\CmsPackage\Content\Data\MetaRow;
use Made\CmsPackage\Content\Enums\MetaField;
use Made\CmsPackage\Content\Enums\MetaFilter;
use Made\CmsPackage\Http\Cms\GoogleResult;
use Made\CmsPackage\Http\Cms\Menu;
use Made\CmsPackage\Http\Cms\MetaLinks;
use Made\CmsPackage\Http\Cms\Shell;
use Made\CmsPackage\Support\Locale;

/**
 * The Meta-overzicht (design 9.1, 17.1, 17.2): every page with how its title and description measure up to what Google
 * shows, in menu order, and the page to work through beside the list.
 */
final class MetaTagsResponse
{
    /**
     * Up to this many points the progress is a mark per point, as in the design; more no one counts at a glance, so the
     * progress is one bar then.
     */
    public const int SEGMENTS = 12;

    /**
     * @param  list<Screen>  $screens  the screens of the editor, in menu order (ListScreens)
     * @param  string|null  $saved  the name of the page just saved, which the panel says once (MetaRedirect)
     */
    public static function make(Editor $editor, array $screens, MetaOverview $overview, ?string $saved = null): Response
    {
        $round = $overview->rows->round();

        return Shell::respond('made-cms-package-launcher::cms.meta.index', $editor, $screens, Screen::MetaTags, [
            'screen' => Screen::MetaTags,
            'intro' => self::intro($overview),
            'workUrl' => $round === [] ? null : MetaLinks::screen($overview->filter, $round[0]->id, panel: true),
            'workLabel' => self::choice('work', count($round)),
            'screenUrl' => Menu::url(Screen::MetaTags),
            'anchor' => MetaLinks::PANEL,
            'filters' => array_map(static fn (MetaFilter $filter): array => [
                'value' => $filter->value,
                'label' => self::choice('filters.'.$filter->value, $overview->count($filter)),
                'pressed' => $filter === $overview->filter,
                'dot' => match ($filter) {
                    MetaFilter::Missing => 'alarm',
                    MetaFilter::Long => 'let',
                    MetaFilter::Ok => 'goed',
                    MetaFilter::All => null,
                },
            ], $overview->offered()),
            'openPage' => $overview->panel?->row->id,
            'done' => $overview->done,
            'finished' => $overview->finished(),
            'rows' => array_map(static fn (MetaRow $row): array => self::row($row, $overview), $overview->listed()),
            'legend' => self::text('list.legend', self::limits()),
            'limits' => self::limits(),
            'panel' => $overview->panel === null ? null : self::panel($overview, $overview->panel),
            'next' => self::nextSteps(),
            'saved' => $saved === null ? null : self::text('panel.saved', ['title' => $saved]),
        ], $overview->rows->tree);
    }

    /**
     * The intro under the heading: what the screen is for, and how far the site is.
     */
    private static function intro(MetaOverview $overview): string
    {
        return self::text('intro.lead').' '.match (true) {
            $overview->shown() === 0 => self::text('intro.none'),
            $overview->finished() => self::choice('intro.finished', $overview->shown()),
            default => self::choice('intro.points', $overview->points()),
        };
    }

    /**
     * A page in the list: its name and address, and the bars of its title and description, or why Google does not
     * show it. It opens the page beside the list and keeps the filter and the progress of the round.
     *
     * @return array<string, mixed>
     */
    private static function row(MetaRow $row, MetaOverview $overview): array
    {
        $measure = $row->measure;

        return [
            'name' => $row->name,
            'path' => $row->path?->value,
            'url' => MetaLinks::screen($overview->filter, $row->id, $overview->done, panel: true),
            'open' => $overview->panel?->row->id === $row->id,
            'absent' => $measure->notShown === null ? null : self::text('list.absent.'.$measure->notShown->value),
            'bars' => $measure->shown() ? [self::bar('title', $measure->title), self::bar('description', $measure->description)] : [],
        ];
    }

    /**
     * The page open beside the list: its place in the round or why it opens on its own, what Google shows of it, the
     * fields with their bars, a proposed description, saving and passing it by.
     *
     * @return array<string, mixed>
     */
    private static function panel(MetaOverview $overview, MetaPanel $panel): array
    {
        $row = $panel->row;
        $measure = $row->measure;
        $place = $panel->place;
        $limits = self::limits();

        return [
            'name' => $row->name,
            'place' => $place === null ? null : [
                'label' => self::text('panel.place', ['position' => $place->position, 'total' => $place->total]),
                'done' => self::text('panel.done', ['done' => $place->done, 'total' => $place->total]),
                'segments' => $place->total <= self::SEGMENTS ? self::segments($place) : null,
                'fill' => (int) round($place->done / $place->total * 100),
            ],
            'single' => $place === null ? self::text('panel.single.'.($measure->notShown->value ?? 'ok')) : null,
            'address' => GoogleResult::address($panel->meta->canonical),
            'shownTitle' => $panel->meta->title,
            'description' => $panel->meta->description,
            'ownTitle' => $panel->ownTitle,
            'fallback' => $panel->fallbackTitle,
            'fields' => [
                'title' => self::field('title', $measure->title),
                'description' => self::field('description', $measure->description),
            ],
            'suggestion' => $panel->suggestion === null ? null : [
                'heading' => self::text('panel.suggestion.'.$panel->suggestion->origin->value),
                'text' => $panel->suggestion->text,
                'fits' => self::text('panel.suggestion.fits', [
                    'count' => MeasureMeta::run(MetaField::Description, $panel->suggestion->text)->length,
                ]),
            ],
            'url' => MetaLinks::update($row->id),
            'filter' => $overview->filter->value,
            'done' => $overview->done,
            'save' => self::text($place === null ? 'panel.save' : 'panel.save_next'),
            'skipUrl' => $place === null || $place->left() <= 1 ? null : MetaLinks::screen(
                $overview->filter,
                $place->next,
                $place->next === null ? 0 : $overview->done,
                panel: true,
            ),
            'config' => [
                'fallback' => $panel->fallbackTitle,
                'limits' => $limits,
                'warn' => [
                    'title' => MetaField::Title->warnAbove(),
                    'description' => MetaField::Description->warnAbove(),
                ],
                'texts' => [
                    'count' => self::text('panel.count'),
                    'empty' => self::text('panel.preview_empty'),
                    'fields' => ['title' => self::text('bar.title'), 'description' => self::text('bar.description')],
                    'said' => [
                        'missing' => self::text('bar.missing'),
                        'long' => self::text('bar.long'),
                        'near' => self::text('bar.near'),
                        'fits' => self::text('bar.fits'),
                    ],
                ],
            ],
        ];
    }

    /**
     * A field of the panel: its counter (":count / :limit", only for the eye) and its bar, which says it in words.
     *
     * @param  'title'|'description'  $name
     * @return array<string, mixed>
     */
    private static function field(string $name, MetaLength $length): array
    {
        return [
            'count' => self::text('panel.count', ['count' => $length->length, 'limit' => $length->limit]),
            'state' => match (true) {
                $length->missing => 'ontbreekt',
                $length->tooLong => 'te-lang',
                default => null,
            },
            'bar' => self::bar($name, $length),
        ];
    }

    /**
     * How a text measures up as a bar: red when it is missing or too long (the whole bar), amber near the limit, green
     * otherwise, and in words for whoever does not see the colour.
     *
     * @param  'title'|'description'  $name
     * @return array{tone: string, fill: int, said: string}
     */
    private static function bar(string $name, MetaLength $length): array
    {
        $state = match (true) {
            $length->missing => 'missing',
            $length->tooLong => 'long',
            $length->nearLimit => 'near',
            default => 'fits',
        };

        return [
            'tone' => match ($state) {
                'missing' => 'ontbreekt',
                'long' => 'te-lang',
                'near' => 'krap',
                'fits' => 'past',
            },
            'fill' => $length->missing || $length->tooLong ? 100 : (int) round($length->length / $length->limit * 100),
            'said' => self::text('bar.'.$state, ['field' => self::text('bar.'.$name), 'count' => $length->length, 'limit' => $length->limit]),
        ];
    }

    /**
     * A mark per point of the round: put in order, the open one, or still to come (or passed by).
     *
     * @return list<string>
     */
    private static function segments(MetaRoundPlace $place): array
    {
        return array_map(static fn (int $point): string => match (true) {
            $point <= $place->done => 'gedaan',
            $point === $place->position => 'actief',
            default => '',
        }, range(1, $place->total));
    }

    /**
     * "Wat je nu kunt doen" on a finished screen: the other screens of Vindbaarheid with open points, each with its
     * number. None has them yet; Alt-teksten (#95) and Redirects (#93) join here, so the block shows only once a line
     * has a real number behind it.
     *
     * @return list<array{label: string, text: string, url: string}>
     */
    private static function nextSteps(): array
    {
        return [];
    }

    /**
     * @return array{title: int, description: int}
     */
    private static function limits(): array
    {
        return [
            'title' => MetaField::Title->limit(),
            'description' => MetaField::Description->limit(),
        ];
    }

    /**
     * @param  array<string, int|string>  $replace
     */
    private static function text(string $key, array $replace = []): string
    {
        return (string) __('made-cms-package::meta.'.$key, $replace, Locale::launcher());
    }

    /**
     * @param  array<string, int|string>  $replace
     */
    private static function choice(string $key, int $count, array $replace = []): string
    {
        return trans_choice('made-cms-package::meta.'.$key, $count, ['count' => $count, ...$replace], Locale::launcher());
    }
}
