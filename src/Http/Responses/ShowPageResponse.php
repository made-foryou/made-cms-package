<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\MeasureMeta;
use Made\CmsPackage\Content\Actions\MeasureShareImage;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Data\EditableStrip;
use Made\CmsPackage\Content\Data\EditableStrips;
use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Content\Data\MenuPlace;
use Made\CmsPackage\Content\Data\MetaLength;
use Made\CmsPackage\Content\Data\PageAttention;
use Made\CmsPackage\Content\Data\PageHistory;
use Made\CmsPackage\Content\Data\PageMedia;
use Made\CmsPackage\Content\Data\PageMeta;
use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Data\PageSummary;
use Made\CmsPackage\Content\Data\PageTree;
use Made\CmsPackage\Content\Data\RestoredVersion;
use Made\CmsPackage\Content\Data\ShareImageDescription;
use Made\CmsPackage\Content\Data\ShareImageFit;
use Made\CmsPackage\Content\Data\VersionSummary;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\MenuPlaceReason;
use Made\CmsPackage\Content\Enums\MetaField;
use Made\CmsPackage\Content\Enums\PageState;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Enums\ShareImageRefusal;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\PageTypes\PageType;
use Made\CmsPackage\Content\PageTypes\PageTypes;
use Made\CmsPackage\Http\Cms\AttentionActions;
use Made\CmsPackage\Http\Cms\CropStep;
use Made\CmsPackage\Http\Cms\Day;
use Made\CmsPackage\Http\Cms\MediaBrowser;
use Made\CmsPackage\Http\Cms\MediaDetails;
use Made\CmsPackage\Http\Cms\MediaLinks;
use Made\CmsPackage\Http\Cms\Menu;
use Made\CmsPackage\Http\Cms\PageLinks;
use Made\CmsPackage\Http\Cms\Shell;
use Made\CmsPackage\Http\Cms\SiteAddress;
use Made\CmsPackage\Http\Cms\UploadField;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;
use Made\CmsPackage\Support\Locale;
use Made\CmsPackage\Support\Site;

/**
 * One page (design 15.2): what it asks for attention, its basics (name, address, place, menu flag and type), what is on
 * it, how Google sees it, how a shared link shows it (design 23), the pages under it, its versions and removing it, with
 * the page in short in the column "In het kort" beside the cards.
 */
final class ShowPageResponse
{
    /**
     * @param  list<Screen>  $screens  the screens of the editor, in menu order (ListScreens)
     * @param  PageTree  $pages  every page of the site
     * @param  PageTree  $parents  the pages this page may lie under
     * @param  PageMeta  $meta  what the head of the page tells Google now (DescribePageMeta)
     * @param  string  $fallbackTitle  the title in Google while the page has none of its own (MakeFallbackTitle)
     * @param  bool  $metaSaved  the card "Hoe Google hem ziet" was saved right before
     * @param  ShareImageDescription  $share  the image a shared link to the page shows now (DescribeShareImage)
     * @param  MediaFolder|null  $folder  the folder the picker and the upload field start in (MediaFolderMemory)
     * @param  bool  $shareSaved  the card "Hoe je link gedeeld wordt" was saved right before
     * @param  PageAttention  $attention  what the page asks for attention (ListPageAttention)
     * @param  PageTypes  $types  the page types of the site, to choose from in the basics
     * @param  PageSummary  $summary  the page in short (SummarizePage), for "Wat erop staat" and "In het kort"
     * @param  PageHistory  $history  the versions of the page (ListPageVersions), for "Wat er eerder gebeurde"
     * @param  RestoredVersion|null  $restored  what putting a version back did right before
     * @param  array<int, string>  $names  the names of the editors the summary and the versions name, by id (NameEditors)
     */
    public static function make(
        Editor $editor,
        array $screens,
        Page $page,
        PageTree $pages,
        PageTree $parents,
        PageMeta $meta,
        string $fallbackTitle,
        bool $metaSaved,
        ShareImageDescription $share,
        ?MediaFolder $folder,
        bool $shareSaved,
        PageAttention $attention,
        PageTypes $types,
        PageSummary $summary,
        PageHistory $history,
        ?RestoredVersion $restored,
        array $names,
    ): Response {
        $node = $pages->find($page->id);
        $path = $page->route?->path;

        return Shell::respond('made-cms-package-launcher::cms.pages.show', $editor, $screens, Screen::Pages, [
            'page' => [
                'title' => $page->title,
                'slug' => $page->slug,
                'path' => $path,
                'state' => PageState::of($page->status, $page->hidden)->value,
                'live' => $page->status === PageStatus::Published,
                'home' => $path === PagePath::HOME,
                'inMenu' => $page->in_menu,
                'hidden' => $page->hidden,
                'parentId' => $page->parent_id,
                'type' => $page->type,
                'typeKnown' => $page->type === null || $types->has($page->type),
            ],
            'types' => array_map(static fn (PageType $type): array => ['key' => $type->key(), 'label' => $type->label()], $types->all()),
            'address' => $path === null ? null : Site::host().($path === PagePath::HOME ? '' : $path),
            'viewUrl' => SiteAddress::of($page),
            'indexUrl' => PageLinks::index(),
            'editUrl' => PageLinks::edit($page),
            'updateUrl' => PageLinks::update($page),
            'duplicateUrl' => PageLinks::duplicate($page),
            'destroyUrl' => PageLinks::destroy($page),
            'visibilityUrl' => PageLinks::visibility($page),
            'childCreateUrl' => PageLinks::create($page->id),
            'parents' => array_map(static fn (PageNode $option): array => [
                'id' => $option->id,
                'title' => $option->title,
                'depth' => $option->depth,
            ], $parents->flatten()),
            'children' => array_map(static fn (PageNode $child): array => [
                'title' => $child->title,
                'path' => $child->path?->value,
                'state' => PageState::of($child->status, $child->hidden)->value,
                'showUrl' => PageLinks::show($child->id),
            ], $node->children ?? []),
            'menuPlace' => self::menuPlace($pages, $pages->menuPlaceOf($page->id)),
            'schedule' => self::schedule($page),
            'keepDays' => (int) config('made-cms-package.pages.keep_deleted_days', 30),
            'meta' => self::meta($page, $screens, $meta, $fallbackTitle, $metaSaved),
            'share' => self::share($page, $screens, $meta, $share, $folder, $shareSaved),
            'attention' => self::attention($page, $screens, $attention, $summary->media),
            'strips' => self::strips($page, $summary->strips),
            'summary' => self::summary($pages, $types, $summary, $names),
            'files' => self::files($page, $screens, $summary->media),
            'versions' => self::versions($page, $history, $restored, $names),
        ], $pages, $page);
    }

    /**
     * The switch "Publiceren op een moment" in the basics: the planned moment in the timezone of the app, as the field
     * of the browser holds it, the line that says when the draft goes live, and the timezone when it is not the one of
     * the Netherlands.
     *
     * @return array{on: bool, value: string|null, hint: string, zone: string|null, publishNowUrl: string}
     */
    private static function schedule(Page $page): array
    {
        $locale = Locale::launcher();
        $text = static fn (string $key, array $replace = []): string => (string) __('made-cms-package::pages.show.schedule.'.$key, $replace, $locale);
        $zone = config()->string('app.timezone');
        $moment = $page->publish_at;
        $live = $page->status === PageStatus::Published;

        $hint = match (true) {
            $moment === null => $text($live ? 'off_live' : 'off_new'),
            $moment->lessThanOrEqualTo(now()) => $text('late', ['at' => Day::at($moment, $locale)]),
            $live && $page->draft_strips === null => $text('on', ['at' => Day::at($moment, $locale)]).' '.$text('nothing'),
            default => $text('on', ['at' => Day::at($moment, $locale)]),
        };

        return [
            'on' => $moment !== null,
            'value' => $moment === null ? null : Day::input($moment),
            'hint' => $hint,
            'zone' => $zone === 'Europe/Amsterdam' ? null : $text('zone', ['zone' => $zone]),
            'publishNowUrl' => PageLinks::publishNow($page),
        ];
    }

    /**
     * The card "Hoe hij ervoor staat" and its line in "In het kort": every point in words, with the way to what solves
     * it only where the editor may go. A page whose one point is that it is empty says so in the line.
     *
     * @param  list<Screen>  $screens
     * @return array{
     *     items: list<array{severity: string, title: string, meta: string, action: string, url: string|null}>,
     *     summary: array{text: string, hint: string|null, flagged: bool},
     * }
     */
    private static function attention(Page $page, array $screens, PageAttention $attention, PageMedia $media): array
    {
        $locale = Locale::launcher();
        $text = static fn (string $key, array $replace = []): string => (string) __('made-cms-package::pages.show.'.$key, $replace, $locale);
        $point = static fn (AttentionItem $item, string $key): string => trans_choice(
            'made-cms-package::pages.show.attention.reasons.'.$item->reason->value.'.'.$key,
            (int) ($item->details['count'] ?? 1),
            self::attentionFacts($item, $media, $locale),
            $locale,
        );
        $empty = $attention->count() === 1 && $attention->items[0]->reason === AttentionReason::Empty;

        return [
            'items' => array_map(static fn (AttentionItem $item): array => [
                'severity' => $item->severity->value,
                'title' => $point($item, 'title'),
                'meta' => $point($item, 'meta'),
                'action' => $point($item, 'action'),
                'url' => AttentionActions::url($item, $page, $screens),
            ], $attention->items),
            'summary' => [
                'text' => $empty
                    ? $text('summary.empty')
                    : trans_choice('made-cms-package::pages.show.summary.attention', $attention->count(), [], $locale),
                'hint' => match (true) {
                    $empty => $text('summary.empty_hint'),
                    $attention->isEmpty() => null,
                    default => $text('summary.attention_hint'),
                },
                'flagged' => ! $attention->isEmpty(),
            ],
        ];
    }

    /**
     * The facts the words of an attention point need: its details, and for photos without an alt text the name of the
     * one the page shows first and how many others there are.
     *
     * @return array<string, int|string>
     */
    private static function attentionFacts(AttentionItem $item, PageMedia $media, string $locale): array
    {
        if ($item->reason !== AttentionReason::ImageWithoutAlt) {
            return $item->details;
        }

        $first = array_values(array_filter(
            $media->files,
            static fn (MediaFile $file): bool => $file->id === ($item->details['media'] ?? null),
        ))[0] ?? null;

        return [
            ...$item->details,
            'name' => $first === null
                ? (string) __('made-cms-package::pages.show.attention.reasons.image_without_alt.unnamed', [], $locale)
                : self::fileName($first),
            'others' => (int) ($item->details['count'] ?? 1) - 1,
        ];
    }

    /**
     * The files on the working copy per kind, "3 foto’s, 1 document"; null for none.
     *
     * @param  callable(string, int, array<string, string>): string  $count
     */
    private static function fileCount(PageMedia $media, callable $count): ?string
    {
        $kinds = array_filter($media->countPerKind());

        return $kinds === [] ? null : implode(', ', array_map(
            static fn (string $kind, int $number): string => $count('files.'.$kind, $number, []),
            array_keys($kinds),
            $kinds,
        ));
    }

    /**
     * The card "Bestanden op deze pagina" (design 15.2): the files on the working copy small, in the order of the page,
     * an amber dot on a photo without an alt text and a line about those. Only an editor who opens Media gets the
     * ways there: a file opens chosen, "Open in Media" searches the library for the name of the page.
     *
     * @param  list<Screen>  $screens
     * @return array{
     *     available: bool,
     *     items: list<array{name: string, kind: string, thumb: string|null, noAlt: bool, url: string|null}>,
     *     searchUrl: string|null,
     *     alt: array{text: string, link: string|null, url: string|null}|null,
     * }
     */
    private static function files(Page $page, array $screens, PageMedia $media): array
    {
        $locale = Locale::launcher();
        $library = in_array(Screen::Media, $screens, true);
        $missing = count($media->withoutAlt);
        $count = static fn (string $key): string => trans_choice('made-cms-package::pages.show.files.'.$key, $missing, [], $locale);

        return [
            'available' => $media->available,
            'items' => array_map(static fn (MediaFile $file): array => [
                'name' => self::fileName($file),
                'kind' => $file->kind->value,
                'thumb' => $file->thumb,
                'noAlt' => in_array($file->id, $media->withoutAlt, true),
                'url' => $library ? MediaLinks::file($file->id) : null,
            ], $media->files),
            'searchUrl' => $library ? MediaLinks::search($page->title) : null,
            'alt' => $missing === 0 ? null : [
                'text' => $count($library ? 'alt' : 'alt_elsewhere'),
                'link' => $library ? $count('alt_link') : null,
                'url' => $library ? MediaLinks::missingAlt($missing, $media->withoutAlt[0]) : null,
            ],
        ];
    }

    /**
     * A file by the name an editor knows it by: its name with the extension of the file a visitor gets.
     */
    private static function fileName(MediaFile $file): string
    {
        return $file->name.'.'.$file->extension();
    }

    /**
     * The card "Wat erop staat": the strips of the working copy as the structure of the edit shell sums them up, each
     * with the way into the shell with it chosen; a strip whose type left the catalogue has none, as the shell cannot
     * open it.
     *
     * @return list<array{label: string, summary: string, fields: string|null, hidden: bool, editUrl: string|null}>
     */
    private static function strips(Page $page, EditableStrips $strips): array
    {
        $locale = Locale::launcher();
        $text = static fn (string $key): string => (string) __('made-cms-package::cms.edit.structure_texts.'.$key, [], $locale);

        return array_map(static fn (EditableStrip $strip): array => [
            'label' => $strip->label,
            'summary' => $strip->known ? ($strip->summary ?? $text('empty_strip')) : $text('unknown_summary'),
            'fields' => $strip->known ? trans_choice('made-cms-package::cms.edit.fields', $strip->fields, [], $locale) : null,
            'hidden' => ! $strip->visible,
            'editUrl' => $strip->known ? PageLinks::edit($page, $strip->id) : null,
        ], $strips->strips);
    }

    /**
     * The column "In het kort": how the page stands in one sentence, the facts about it, and how saving works here.
     *
     * @param  array<int, string>  $names
     * @return array{line: string, facts: list<array{label: string, value: string}>, note: string}
     */
    private static function summary(PageTree $pages, PageTypes $types, PageSummary $summary, array $names): array
    {
        $locale = Locale::launcher();
        $text = static fn (string $key, array $replace = []): string => (string) __('made-cms-package::pages.show.summary.'.$key, $replace, $locale);
        $count = static fn (string $key, int $count, array $replace = []): string => trans_choice('made-cms-package::pages.show.summary.'.$key, $count, $replace, $locale);
        $day = static fn (?CarbonImmutable $moment): string => $moment === null ? '' : Day::long($moment, $locale);
        $created = $day($summary->createdAt);
        $creator = $summary->createdBy === null ? null : ($names[$summary->createdBy] ?? null);

        $facts = [
            ['label' => $text('type'), 'value' => self::typeName($types, $summary->type, $locale)],
            ['label' => $text('parent'), 'value' => $summary->parentTitle ?? (string) __('made-cms-package::pages.show.parent.root', [], $locale)],
            ['label' => $text('menu.label'), 'value' => self::menuName($pages, $summary->menuPlace, $text)],
            ['label' => $text('children.label'), 'value' => $count('children.count', $summary->children)],
            ['label' => $text('strips.label'), 'value' => $count('strips.count', count($summary->strips->strips), [
                'fields' => $count('strips.fields', $summary->fields),
            ])],
        ];

        if ($summary->media->available) {
            $facts[] = ['label' => $text('files.label'), 'value' => self::fileCount($summary->media, $count) ?? $text('files.none')];
        }

        if ($summary->createdAt !== null) {
            $facts[] = [
                'label' => $text('created.label'),
                'value' => $creator === null ? $created : $text('created.by', ['date' => $created, 'name' => $creator]),
            ];
        }

        return [
            'line' => self::line($summary, $names, $day, $text),
            'facts' => $facts,
            'note' => $text('note'),
        ];
    }

    /**
     * How the page stands in one sentence: a draft since when and by whom it was created, a page visitors can open
     * when it was published and changed last, and by whom; a name that is not known is left out.
     *
     * @param  array<int, string>  $names
     * @param  callable(?CarbonImmutable): string  $day
     * @param  callable(string, array<string, string>): string  $text
     */
    private static function line(PageSummary $summary, array $names, callable $day, callable $text): string
    {
        if ($summary->state === PageState::Draft) {
            $name = $summary->createdBy === null ? null : ($names[$summary->createdBy] ?? null);

            return $text('line.draft'.($name === null ? '_unknown' : ''), ['created' => $day($summary->createdAt), 'name' => (string) $name]);
        }

        $name = $summary->updatedBy === null ? null : ($names[$summary->updatedBy] ?? null);

        return $text('line.'.$summary->state->value.($name === null ? '_unknown' : ''), [
            'published' => $day($summary->publishedAt),
            'updated' => $day($summary->updatedAt ?? $summary->publishedAt),
            'name' => (string) $name,
        ]);
    }

    /**
     * The card "Wat er eerder gebeurde": every version with what it changed, its number, when and by whom it went live
     * and "nu live" at the one visitors see, the way back for the others (asking first when a draft would go), and what
     * putting one back did right before, with the way into the edit shell to look at it.
     *
     * @param  array<int, string>  $names
     * @return array{
     *     items: list<array{title: string, meta: string, live: bool, restoreUrl: string|null, label: string, confirm: string}>,
     *     empty: string|null,
     *     draft: bool,
     *     restored: string|null,
     *     editUrl: string,
     * }
     */
    private static function versions(Page $page, PageHistory $history, ?RestoredVersion $restored, array $names): array
    {
        $locale = Locale::launcher();
        $text = static fn (string $key, array $replace = []): string => (string) __('made-cms-package::pages.show.versions.'.$key, $replace, $locale);

        $items = array_map(static function (VersionSummary $version) use ($page, $names, $text, $locale): array {
            $name = $version->publishedBy === null ? null : ($names[$version->publishedBy] ?? null);
            $meta = array_filter([
                $text('number', ['number' => (string) $version->number]),
                Day::moment($version->publishedAt, $locale),
                $name,
                $version->live ? $text('live') : null,
            ], static fn (?string $part): bool => $part !== null);

            return [
                'title' => self::versionTitle($version, $text, $locale),
                'meta' => implode(' · ', $meta),
                'live' => $version->live,
                'restoreUrl' => $version->live ? null : PageLinks::restoreVersion($page, $version->number),
                'label' => $text('restore_label', ['number' => (string) $version->number]),
                'confirm' => $text('confirm_button', ['number' => (string) $version->number]),
            ];
        }, $history->versions);

        return [
            'items' => $items,
            'empty' => match (true) {
                $items !== [] => null,
                $page->status === PageStatus::Published => $text('none'),
                default => $text('never'),
            },
            'draft' => $history->draft,
            'restored' => $restored === null ? null : $text(
                'restored'.($restored->draft ? '' : '_same').($restored->replacedDraft ? '_replaced' : ''),
                ['number' => (string) $restored->number],
            ),
            'editUrl' => PageLinks::edit($page),
        ];
    }

    /**
     * What a version changed in one line: the first publication, else every kind of change with up to three strips by
     * name ("2 stroken gewijzigd: ‘Teams’ en ‘Competitie’").
     *
     * @param  callable(string, array<string, string>): string  $text
     */
    private static function versionTitle(VersionSummary $version, callable $text, string $locale): string
    {
        if ($version->first) {
            return $text('first', []);
        }

        $parts = [];

        foreach (['added' => $version->added, 'changed' => $version->changed, 'moved' => $version->moved, 'removed' => $version->removed] as $kind => $names) {
            if ($names === []) {
                continue;
            }

            $parts[] = $text('part', [
                'change' => trans_choice('made-cms-package::pages.show.versions.'.$kind, count($names), [], $locale),
                'names' => self::stripNames($names, $text, $locale),
            ]);
        }

        return $parts === [] ? $text('published', []) : implode('; ', $parts);
    }

    /**
     * @param  list<string>  $names
     * @param  callable(string, array<string, string>): string  $text
     */
    private static function stripNames(array $names, callable $text, string $locale): string
    {
        $shown = array_map(static fn (string $name): string => $text('name', ['name' => $name]), array_slice($names, 0, 3));
        $rest = count($names) - count($shown);

        if ($rest > 0) {
            return implode(', ', $shown).' '.trans_choice('made-cms-package::pages.show.versions.more', $rest, [], $locale);
        }

        $last = (string) array_pop($shown);

        return $shown === [] ? $last : implode(', ', $shown).' '.$text('and', []).' '.$last;
    }

    private static function typeName(PageTypes $types, ?string $type, string $locale): string
    {
        return match (true) {
            $type === null => (string) __('made-cms-package::pages.types.ordinary', [], $locale),
            $types->has($type) => $types->find($type)->label(),
            default => (string) __('made-cms-package::pages.show.type.unknown', ['key' => $type], $locale),
        };
    }

    /**
     * @param  callable(string, array<string, int|string>): string  $text
     */
    private static function menuName(PageTree $pages, ?MenuPlace $place, callable $text): string
    {
        $hint = self::menuPlace($pages, $place);

        return $hint === null ? $text('menu.off', []) : $text('menu.'.$hint['hint'], $hint['replace']);
    }

    /**
     * The card "Hoe Google hem ziet": what the page holds, the result in Google, the counters as they start, and what
     * cms.js needs to keep the counters and the result up to date while the editor types.
     *
     * @param  list<Screen>  $screens
     * @return array<string, mixed>
     */
    private static function meta(Page $page, array $screens, PageMeta $meta, string $fallbackTitle, bool $saved): array
    {
        $locale = Locale::launcher();
        $text = static fn (string $key): string => (string) __('made-cms-package::pages.show.meta.'.$key, [], $locale);

        return [
            'title' => $page->meta_title,
            'description' => $page->meta_description,
            'fallback' => $fallbackTitle,
            'shownTitle' => $meta->title,
            'inSitemap' => $page->in_sitemap,
            'indexable' => $page->indexable,
            'hidden' => $page->hidden,
            'address' => self::googleAddress($meta->canonical),
            'titleCount' => self::counter(MeasureMeta::run(MetaField::Title, $meta->title), fallback: $page->meta_title === null),
            'descriptionCount' => self::counter(MeasureMeta::run(MetaField::Description, $meta->description), fallback: false),
            'url' => PageLinks::meta($page),
            'overviewUrl' => in_array(Screen::MetaTags, $screens, true) ? Menu::url(Screen::MetaTags) : null,
            'saved' => $saved,
            'config' => [
                'fallback' => $fallbackTitle,
                'limits' => ['title' => MetaField::Title->limit(), 'description' => MetaField::Description->limit()],
                'texts' => [
                    'count' => $text('count'),
                    'fallback' => $text('count_fallback'),
                    'missing' => $text('count_missing'),
                    'long' => $text('count_long'),
                    'empty' => $text('preview_empty'),
                    'sitemap' => $text('in_sitemap.hint'),
                    'sitemap_noindex' => $text('in_sitemap.noindex'),
                ],
            ],
        ];
    }

    /**
     * The card "Hoe je link gedeeld wordt": the shared link as an app shows it (the image that is shared, the domain,
     * the title and the description from Google), the share image of the page, what to warn about, and what share.js
     * needs to follow a choice of the picker, an upload and a crop. The picker, the upload field and the crop step come
     * along, because the card chooses with them; moving a file in the crop step is for whoever opens the media library.
     *
     * @param  list<Screen>  $screens
     * @return array<string, mixed>
     */
    private static function share(Page $page, array $screens, PageMeta $meta, ShareImageDescription $share, ?MediaFolder $folder, bool $saved): array
    {
        $locale = Locale::launcher();
        $media = in_array(Screen::Media, $screens, true);
        $file = $share->file === null ? null : MediaDetails::of($share->file);
        $fit = $share->own === null ? null : MeasureShareImage::run($share->own->width, $share->own->height);
        $texts = self::shareTexts($locale);
        $mediaUrl = $media ? Menu::url(Screen::Media) : null;

        return [
            'url' => PageLinks::shareImage($page),
            'value' => $page->share_image_id,
            'file' => $file,
            'usable' => $share->own !== null,
            'preview' => self::sharePreview($share, $file),
            'domain' => self::host($meta->canonical),
            'title' => $meta->title,
            'description' => $meta->description,
            'indexable' => $meta->indexable,
            'notices' => self::shareNotices($share, $file, $fit, $mediaUrl, $texts),
            'saved' => $saved,
            'upload' => UploadField::image($folder, crop: true),
            'picker' => MediaBrowser::picker($folder),
            'crop' => CropStep::make(moves: $media),
            'script' => LauncherAssetController::url('share.js'),
            'config' => [
                'value' => $page->share_image_id,
                'file' => $file,
                'refusal' => $share->refusal?->value,
                'fallback' => $share->fallback?->url,
                'media_url' => $mediaUrl,
                'fit' => [
                    'width' => MeasureShareImage::WIDTH,
                    'height' => MeasureShareImage::HEIGHT,
                    'min_width' => MeasureShareImage::MIN_WIDTH,
                    'min_height' => MeasureShareImage::MIN_HEIGHT,
                    'tolerance' => MeasureShareImage::TOLERANCE,
                ],
                'texts' => $texts,
            ],
        ];
    }

    /**
     * The image the preview shows: what a shared link shows now, small (the thumbnail of the share image, else the
     * default of the site); null for none.
     *
     * @param  array<string, mixed>|null  $file
     */
    private static function sharePreview(ShareImageDescription $share, ?array $file): ?string
    {
        if ($share->own !== null) {
            $thumb = $file['thumb'] ?? null;

            return is_string($thumb) ? $thumb : $share->own->url;
        }

        return $share->fallback?->url;
    }

    /**
     * What the card warns about, in the order share.js gives it: a file the link cannot show; else a small image or how
     * much of another shape is cut away, and a missing alt text; without an image of its own, the default of the site
     * or that there is none.
     *
     * @param  array<string, mixed>|null  $file
     * @param  array<string, string>  $texts
     * @return list<array{tone: string, title: string, text: string, link: array{text: string, href: string}|null}>
     */
    private static function shareNotices(ShareImageDescription $share, ?array $file, ?ShareImageFit $fit, ?string $mediaUrl, array $texts): array
    {
        if ($share->refusal !== null) {
            return [self::shareNotice('let', $texts['unusable_title'], $texts['refusal_'.$share->refusal->value])];
        }

        if ($share->own === null || $file === null || $fit === null) {
            return [$share->fallback === null ? self::shareNotice('let', '', $texts['none']) : self::shareNotice('', '', $texts['default'])];
        }

        $croppable = ($file['croppable'] ?? false) === true;
        $notices = [];

        if ($fit->tooSmall) {
            $notices[] = self::shareNotice('let', $texts['small_title'], strtr($texts['small'], [':width' => (string) $share->own->width, ':height' => (string) $share->own->height]));
        } elseif ($fit->cut > 0) {
            $cut = strtr($texts['cut_'.($fit->axis ?? 'y')], [':cut' => (string) $fit->cut]);
            $notices[] = self::shareNotice('let', $texts['cut_title'], implode(' ', array_filter([$cut, $croppable ? $texts['cut_crop'] : null, $texts['go_on']])));
        }

        if ($share->own->alt === null) {
            $notices[] = self::shareNotice(
                'let',
                $texts['alt_title'],
                $mediaUrl === null && $croppable ? $texts['alt_crop'] : $texts['alt'],
                $mediaUrl === null ? null : ['text' => $texts['alt_link'], 'href' => $mediaUrl.'?file='.$share->file?->id],
            );
        }

        return $notices;
    }

    /**
     * @param  ''|'let'  $tone  the colour of its dot: the accent, or amber for something to look at
     * @param  array{text: string, href: string}|null  $link
     * @return array{tone: string, title: string, text: string, link: array{text: string, href: string}|null}
     */
    private static function shareNotice(string $tone, string $title, string $text, ?array $link = null): array
    {
        return ['tone' => $tone, 'title' => $title, 'text' => $text, 'link' => $link];
    }

    /**
     * The texts of the card that share.js fills in too, the reasons a file is not shared among them.
     *
     * @return array<string, string>
     */
    private static function shareTexts(string $locale): array
    {
        $notices = __('made-cms-package::pages.show.share.notices', [], $locale);
        $text = static fn (string $key): string => (string) __('made-cms-package::pages.show.share.'.$key, [], $locale);
        $refusals = [];

        foreach (ShareImageRefusal::cases() as $refusal) {
            $refusals['refusal_'.$refusal->value] = (string) __('made-cms-package::pages.errors.share_image_'.$refusal->value, [], $locale);
        }

        return [
            ...(is_array($notices) ? array_map(strval(...), $notices) : []),
            ...$refusals,
            'meta' => $text('meta'),
            'alt_line' => $text('alt'),
            'no_alt_line' => $text('no_alt'),
            'field' => $text('label'),
            'place' => $text('heading'),
            'hint' => $text('hint'),
        ];
    }

    /**
     * The text under a field and its state, in the order cms.js follows: missing, too long, the default title.
     *
     * @return array{key: string, replace: array{count: int, limit: int}, state: 'ontbreekt'|'te-lang'|null}
     */
    private static function counter(MetaLength $length, bool $fallback): array
    {
        [$key, $state] = match (true) {
            $length->missing => ['count_missing', 'ontbreekt'],
            $length->tooLong => ['count_long', 'te-lang'],
            $fallback => ['count_fallback', null],
            default => ['count', null],
        };

        return ['key' => $key, 'replace' => ['count' => $length->length, 'limit' => $length->limit], 'state' => $state];
    }

    /**
     * The address above a result in Google: the host and the parts of the path, "ttvmerwestad.nl › teams".
     */
    private static function googleAddress(?string $canonical): string
    {
        $path = (string) parse_url((string) $canonical, PHP_URL_PATH);
        $parts = array_map(rawurldecode(...), array_filter(explode('/', $path), static fn (string $part): bool => $part !== ''));

        return implode(' › ', [self::host($canonical), ...$parts]);
    }

    /**
     * The host of the canonical address, "ttvmerwestad.nl", else the host of the site.
     */
    private static function host(?string $canonical): string
    {
        $host = parse_url((string) $canonical, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : Site::host();
    }

    /**
     * What the menu switch says about the place of the page in the menu of the site; null for a page outside the menu.
     *
     * @return array{hint: string, replace: array<string, int|string>}|null
     */
    private static function menuPlace(PageTree $pages, ?MenuPlace $place): ?array
    {
        if ($place === null) {
            return null;
        }

        return [
            'hint' => $place->reason->value,
            'replace' => match ($place->reason) {
                MenuPlaceReason::Position, MenuPlaceReason::Publish => ['position' => (int) $place->position, 'total' => (int) $place->total],
                MenuPlaceReason::Parent => ['parent' => $pages->find((int) $place->parentId)->title ?? ''],
                MenuPlaceReason::WhileHidden => [],
            },
        ];
    }
}
