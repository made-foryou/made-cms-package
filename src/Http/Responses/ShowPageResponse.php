<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\Response;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\MeasureMeta;
use Made\CmsPackage\Content\Data\MetaLength;
use Made\CmsPackage\Content\Data\PageMeta;
use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Data\PageTree;
use Made\CmsPackage\Content\Enums\MetaField;
use Made\CmsPackage\Content\Enums\PageState;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Cms\Menu;
use Made\CmsPackage\Http\Cms\PageLinks;
use Made\CmsPackage\Http\Cms\Shell;
use Made\CmsPackage\Http\Cms\SiteAddress;
use Made\CmsPackage\Support\Locale;
use Made\CmsPackage\Support\Site;

/**
 * One page (design 15.2): its basics (name, address, place and menu flag), how Google sees it, the pages under it,
 * and removing it.
 */
final class ShowPageResponse
{
    /**
     * @param  list<Screen>  $screens  the screens of the editor, in menu order (ListScreens)
     * @param  PageTree  $pages  every page of the site
     * @param  PageTree  $parents  the pages this page may lie under
     * @param  PageTree  $menu  the menu of the site (ListMenuPages)
     * @param  PageMeta  $meta  what the head of the page tells Google now (DescribePageMeta)
     * @param  string  $fallbackTitle  the title in Google while the page has none of its own (MakeFallbackTitle)
     * @param  bool  $metaSaved  the card "Hoe Google hem ziet" was saved right before
     */
    public static function make(
        Editor $editor,
        array $screens,
        Page $page,
        PageTree $pages,
        PageTree $parents,
        PageTree $menu,
        PageMeta $meta,
        string $fallbackTitle,
        bool $metaSaved,
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
            ],
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
            'menuPlace' => self::menuPlace($pages, $menu, $node),
            'keepDays' => (int) config('made-cms-package.pages.keep_deleted_days', 30),
            'meta' => self::meta($page, $screens, $meta, $fallbackTitle, $metaSaved),
        ], $pages, $page);
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
        $host = parse_url((string) $canonical, PHP_URL_HOST);
        $path = (string) parse_url((string) $canonical, PHP_URL_PATH);
        $parts = array_map(rawurldecode(...), array_filter(explode('/', $path), static fn (string $part): bool => $part !== ''));

        return implode(' › ', [is_string($host) && $host !== '' ? $host : Site::host(), ...$parts]);
    }

    /**
     * What the menu switch says about the place of the page in the menu of the site; null for a page outside the menu.
     * Only the pages the site shows in its menu count: a hidden page has no place while it is hidden, a draft gets the
     * place it takes once published, and a page under a page the menu of the site leaves out names that page.
     *
     * @return array{hint: 'position'|'publish'|'parent'|'while_hidden', replace: array<string, int|string>}|null
     */
    private static function menuPlace(PageTree $pages, PageTree $menu, ?PageNode $node): ?array
    {
        if ($node === null || ! $node->inMenu) {
            return null;
        }

        if ($node->hidden) {
            return ['hint' => 'while_hidden', 'replace' => []];
        }

        $shown = array_fill_keys(array_map(static fn (PageNode $item): int => $item->id, $menu->flatten()), true);
        $parent = $node->parentId === null ? null : $pages->find($node->parentId);

        if ($parent !== null && ! isset($shown[$parent->id])) {
            return ['hint' => 'parent', 'replace' => ['parent' => $parent->title]];
        }

        $siblings = array_filter(
            $parent === null ? $pages->roots : $parent->children,
            static fn (PageNode $sibling): bool => $sibling->id === $node->id || isset($shown[$sibling->id]),
        );
        $ids = array_values(array_map(static fn (PageNode $sibling): int => $sibling->id, $siblings));

        return [
            'hint' => isset($shown[$node->id]) ? 'position' : 'publish',
            'replace' => ['position' => (int) array_search($node->id, $ids, true) + 1, 'total' => count($ids)],
        ];
    }
}
