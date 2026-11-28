<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\Response;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Data\BuiltSitemap;
use Made\CmsPackage\Content\Data\SitemapOverview;
use Made\CmsPackage\Content\Data\SitemapRow;
use Made\CmsPackage\Content\Data\SitePrefixes;
use Made\CmsPackage\Content\Enums\SitemapCause;
use Made\CmsPackage\Content\Enums\SitemapErrorReason;
use Made\CmsPackage\Content\Enums\SitemapStanding;
use Made\CmsPackage\Content\Models\Sitemap;
use Made\CmsPackage\Http\Cms\Day;
use Made\CmsPackage\Http\Cms\PageLinks;
use Made\CmsPackage\Http\Cms\Shell;
use Made\CmsPackage\Http\Cms\SitemapLinks;
use Made\CmsPackage\Support\Locale;
use Made\CmsPackage\Support\Site;

/**
 * The screen of the sitemap (design 9.6, 17.3, 17.12, 17.13; dark 10.5, 18.3, 18.12, 18.13), as the design shows it
 * without the data of Google: how it stands, the file itself, the files of the site that go before it, and every page
 * with whether it is in it and why not.
 */
final class SitemapScreenResponse
{
    /**
     * The lines of the file the screen shows; the rest is the dots under them.
     */
    public const int HEAD_LINES = 14;

    /**
     * How many pages a sentence names; the others are counted.
     */
    public const int NAMED = 3;

    /**
     * @param  list<Screen>  $screens  the screens of the editor, in menu order (ListScreens)
     * @param  string|null  $builder  the name of the editor who made the sitemap by hand (NameEditors)
     * @param  int|null  $saved  the page whose switch was just saved (SitemapRedirect)
     * @param  BuiltSitemap|null  $built  what making the sitemap again just gave (SitemapRedirect)
     */
    public static function make(Editor $editor, array $screens, SitemapOverview $overview, ?string $builder = null, ?int $saved = null, ?BuiltSitemap $built = null): Response
    {
        $sitemap = $overview->sitemap;
        $cause = $sitemap === null ? '' : self::cause($sitemap, $overview->causePage, $builder);

        return Shell::respond('made-cms-package-launcher::cms.sitemap.index', $editor, $screens, Screen::Sitemap, [
            'screen' => Screen::Sitemap,
            'intro' => self::text('intro'),
            'screenUrl' => SitemapLinks::screen(),
            'group' => $overview->prefix,
            'groups' => count($overview->prefixes) > 1 ? array_map(static fn (string $prefix): array => [
                'value' => $prefix,
                'label' => SitePrefixes::path($prefix, 'sitemap.xml'),
                'pressed' => $prefix === $overview->prefix,
            ], $overview->prefixes) : [],
            'cards' => $sitemap === null ? [] : self::cards($overview, $sitemap, $cause),
            'file' => $sitemap === null ? null : self::file($overview, $sitemap, $cause, $built),
            'rows' => array_map(static fn (SitemapRow $row): array => self::row($row, $overview->prefix, $screens, $saved), $overview->rows->rows),
        ], $overview->rows->tree);
    }

    /**
     * How it stands: the addresses in the sitemap with when it was made, the pages kept out on purpose by name, and
     * when Googlebot fetched it, counted by the CMS itself.
     *
     * @return list<array<string, mixed>>
     */
    private static function cards(SitemapOverview $overview, Sitemap $sitemap, string $cause): array
    {
        $out = $overview->rows->deliberatelyOut();
        $names = array_map(static fn (SitemapRow $row): string => $row->name, $out);

        return [
            [
                'tone' => 'nadruk',
                'count' => $sitemap->address_count,
                'text' => self::choice('state.addresses', $sitemap->address_count),
                'sub' => self::text('state.made', ['moment' => Day::moment($sitemap->built_at, Locale::launcher()), 'cause' => $cause]),
            ],
            [
                'tone' => null,
                'count' => count($out),
                'text' => self::choice('state.deliberate', count($out)),
                'sub' => $names === [] ? self::text('state.deliberate_none') : self::names($names),
            ],
            self::fetches($overview),
        ];
    }

    /**
     * When Googlebot fetched the sitemap of the group, as the CMS counted it; in amber when it never did within the
     * counts the CMS keeps, with where Googlebot did come and the likely reason.
     *
     * @return array<string, mixed>
     */
    private static function fetches(SitemapOverview $overview): array
    {
        $fetches = $overview->fetches;
        $sitemapPath = SitePrefixes::path($overview->prefix, 'sitemap.xml');
        $robots = $overview->files?->robots;
        $warned = $overview->files?->sitemap !== null || ($robots !== null && ! $robots->namesCmsSitemap);

        if ($fetches->lastFetch !== null) {
            $tone = 'goed';
            $heading = self::text('state.fetched', ['when' => Day::ago($fetches->lastFetch, Locale::launcher())]);
            $notes = [
                self::text('state.fetched_note'),
                self::choice('state.changed', $fetches->changedSince),
                $robots === null || $robots->namesCmsSitemap ? self::text('state.found') : null,
            ];
        } else {
            $tone = 'let';
            $heading = self::text('state.not_fetched');
            $notes = [
                $fetches->countingSince === null
                    ? self::text('state.counting_nothing')
                    : self::text('state.counting_since', ['day' => Day::long($fetches->countingSince, Locale::launcher())]),
                $fetches->googlebotPages > 0 ? self::choice('state.googlebot_pages', $fetches->googlebotPages, ['path' => $sitemapPath]) : null,
                $warned ? self::text('state.probably_warning') : null,
            ];
        }

        return [
            'tone' => 'teller '.$tone,
            'dot' => $tone,
            'heading' => $heading,
            'sub' => implode(' ', array_filter($notes, static fn (?string $note): bool => $note !== null)),
            'files' => [
                self::fileLink($sitemapPath),
                self::fileLink('/robots.txt'),
            ],
        ];
    }

    /**
     * The file itself: when it was made and why, its addresses and errors (or what making it again just gave), how
     * it starts, and what belongs in it but is left out.
     *
     * @return array<string, mixed>
     */
    private static function file(SitemapOverview $overview, Sitemap $sitemap, string $cause, ?BuiltSitemap $built): array
    {
        $counts = [
            'addresses' => self::choice('file.addresses', $sitemap->address_count),
            'errors' => self::choice('file.errors', count($sitemap->errors)),
        ];
        $status = self::text('file.status', ['moment' => Day::moment($sitemap->built_at, Locale::launcher()), 'cause' => $cause])
            .' · '.$counts['addresses'].(count($sitemap->errors) > 0 ? ' · '.$counts['errors'] : '');
        $lines = explode("\n", rtrim($sitemap->xml, "\n"));
        $out = array_map(static fn (SitemapRow $row): string => $row->name, $overview->rows->deliberatelyOut());

        return [
            'anchor' => SitemapLinks::FILE,
            'status' => $built === null ? $status : self::text($built->changed ? 'file.built' : 'file.unchanged', [
                'addresses' => self::choice('file.addresses', $built->addresses),
                'errors' => self::choice('file.errors', $built->errors),
            ]),
            'built' => $built !== null,
            'buildUrl' => SitemapLinks::build(),
            'button' => self::text($built === null ? 'file.rebuild' : 'file.rebuild_again'),
            'hint' => self::text($built === null ? 'file.rebuild_hint' : 'file.built_hint'),
            'head' => implode("\n", array_slice($lines, 0, self::HEAD_LINES)).(count($lines) > self::HEAD_LINES ? "\n…" : ''),
            'url' => Site::url(SitePrefixes::path($overview->prefix, 'sitemap.xml')),
            'summary' => implode(' ', array_filter([
                self::choice('file.summary_pages', $sitemap->address_count),
                $out === [] ? null : self::choice('file.summary_out', count($out), ['names' => self::names($out)]),
                self::text('file.summary_dates'),
            ])),
            'errors' => array_map(static fn (array $error): array => [
                'label' => $error['label'],
                'path' => $error['path'],
                'reason' => self::text('file.error.'.(SitemapErrorReason::tryFrom($error['reason']) ?? SitemapErrorReason::NoAddress)->value),
            ], $sitemap->errors),
        ];
    }

    /**
     * A page in the list: its name, address and depth, when it was last published, whether it is in the sitemap and
     * why not, and its switch where that decides, else where to change it.
     *
     * @param  list<Screen>  $screens
     * @return array<string, mixed>
     */
    private static function row(SitemapRow $row, string $group, array $screens, ?int $saved): array
    {
        $standing = $row->standing;

        return [
            'id' => $row->id,
            'anchor' => SitemapLinks::row($row->id),
            'name' => $row->name,
            'path' => $row->path?->value,
            'depth' => min($row->depth, 4),
            'modified' => $row->publishedAt === null ? self::text('list.never') : Day::of($row->publishedAt, Locale::launcher()),
            'reason' => self::text('list.reasons.'.$standing->value),
            'tone' => match ($standing) {
                SitemapStanding::In => 'goed',
                SitemapStanding::Blocked, SitemapStanding::NoAddress => 'let',
                default => 'stil',
            },
            'choice' => $standing->isChoice() ? [
                'url' => SitemapLinks::update($row->id),
                'group' => $group,
                'checked' => $standing->isIn(),
                'label' => self::text('list.switch_label', ['page' => $row->name]),
                'saved' => $saved === $row->id,
            ] : null,
            'elsewhere' => match (true) {
                $standing->isChoice() => null,
                $standing === SitemapStanding::Blocked => ['text' => self::text('list.at_rules'), 'url' => null, 'label' => null],
                default => [
                    'text' => self::text('list.at_page'),
                    'url' => in_array(Screen::Pages, $screens, true) ? PageLinks::show($row->id) : null,
                    'label' => self::text('list.at_page_label', ['page' => $row->name]),
                ],
            },
        ];
    }

    /**
     * What led to the sitemap, after "Gemaakt vandaag, 11:20,".
     */
    private static function cause(Sitemap $sitemap, ?string $page, ?string $builder): string
    {
        $page ??= self::text('causes.page_unknown');

        return match ($sitemap->cause) {
            SitemapCause::Command => self::text('causes.command'),
            SitemapCause::Requested => self::text('causes.requested'),
            SitemapCause::Manual => $builder === null ? self::text('causes.manual') : self::text('causes.manual_by', ['editor' => $builder]),
            default => self::text('causes.'.$sitemap->cause->value, ['page' => $page]),
        };
    }

    /**
     * A file the site serves, as an address that opens it and as the host and path a person reads.
     *
     * @return array{url: string, label: string}
     */
    private static function fileLink(string $path): array
    {
        return ['url' => Site::url($path), 'label' => Site::host().$path];
    }

    /**
     * The names in a sentence: "A", "A en B", "A, B en C", and past three the others counted: "A, B, C en 2 andere".
     *
     * @param  list<string>  $names
     */
    private static function names(array $names): string
    {
        $named = array_slice($names, 0, self::NAMED);
        $others = count($names) - count($named);

        if ($others > 0) {
            return implode(', ', $named).' '.self::text('and').' '.self::choice('others', $others);
        }

        $last = array_pop($named);

        return $named === [] ? (string) $last : implode(', ', $named).' '.self::text('and').' '.$last;
    }

    /**
     * @param  array<string, int|string>  $replace
     */
    private static function text(string $key, array $replace = []): string
    {
        return (string) __('made-cms-package::sitemap.screen.'.$key, $replace, Locale::launcher());
    }

    /**
     * @param  array<string, int|string>  $replace
     */
    private static function choice(string $key, int $count, array $replace = []): string
    {
        return trans_choice('made-cms-package::sitemap.screen.'.$key, $count, ['count' => $count, ...$replace], Locale::launcher());
    }
}
