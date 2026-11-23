<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\Response;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\MeasureMeta;
use Made\CmsPackage\Content\Actions\MeasureShareImage;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Data\MenuPlace;
use Made\CmsPackage\Content\Data\MetaLength;
use Made\CmsPackage\Content\Data\PageAttention;
use Made\CmsPackage\Content\Data\PageMeta;
use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Data\PageTree;
use Made\CmsPackage\Content\Data\ShareImageDescription;
use Made\CmsPackage\Content\Data\ShareImageFit;
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
use Made\CmsPackage\Http\Cms\MediaBrowser;
use Made\CmsPackage\Http\Cms\MediaDetails;
use Made\CmsPackage\Http\Cms\Menu;
use Made\CmsPackage\Http\Cms\PageLinks;
use Made\CmsPackage\Http\Cms\Shell;
use Made\CmsPackage\Http\Cms\SiteAddress;
use Made\CmsPackage\Http\Cms\UploadField;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;
use Made\CmsPackage\Support\Locale;
use Made\CmsPackage\Support\Site;

/**
 * One page (design 15.2): what it asks for attention, also in one line in the column "In het kort" beside the cards,
 * its basics (name, address, place, menu flag and type), how Google sees it, how a shared link shows it (design 23), the
 * pages under it, and removing it.
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
            'keepDays' => (int) config('made-cms-package.pages.keep_deleted_days', 30),
            'meta' => self::meta($page, $screens, $meta, $fallbackTitle, $metaSaved),
            'share' => self::share($page, $screens, $meta, $share, $folder, $shareSaved),
            'attention' => self::attention($page, $screens, $attention),
        ], $pages, $page);
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
    private static function attention(Page $page, array $screens, PageAttention $attention): array
    {
        $locale = Locale::launcher();
        $text = static fn (string $key, array $replace = []): string => (string) __('made-cms-package::pages.show.'.$key, $replace, $locale);
        $empty = $attention->count() === 1 && $attention->items[0]->reason === AttentionReason::Empty;

        return [
            'items' => array_map(static fn (AttentionItem $item): array => [
                'severity' => $item->severity->value,
                'title' => $text('attention.reasons.'.$item->reason->value.'.title', $item->details),
                'meta' => $text('attention.reasons.'.$item->reason->value.'.meta', $item->details),
                'action' => $text('attention.reasons.'.$item->reason->value.'.action', $item->details),
                'url' => AttentionActions::url($item->reason, $page, $screens),
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
