<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\Response;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Data\PageTree;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Cms\PageLinks;
use Made\CmsPackage\Http\Cms\Shell;
use Made\CmsPackage\Support\Site;

/**
 * One page (design 15.2, the basics): its name, address, place and menu flag, the pages under it, and removing it.
 */
final class ShowPageResponse
{
    /**
     * @param  list<Screen>  $screens  the screens of the editor, in menu order (ListScreens)
     * @param  PageTree  $pages  every page of the site
     * @param  PageTree  $parents  the pages this page may lie under
     */
    public static function make(Editor $editor, array $screens, Page $page, PageTree $pages, PageTree $parents): Response
    {
        $node = $pages->find($page->id);
        $path = $page->route?->path;

        return Shell::respond('made-cms-package-launcher::cms.pages.show', $editor, $screens, Screen::Pages, [
            'page' => [
                'title' => $page->title,
                'slug' => $page->slug,
                'path' => $path,
                'status' => $page->status->value,
                'live' => $page->status === PageStatus::Published,
                'home' => $path === PagePath::HOME,
                'inMenu' => $page->in_menu,
                'parentId' => $page->parent_id,
            ],
            'address' => $path === null ? null : Site::host().($path === PagePath::HOME ? '' : $path),
            'viewUrl' => $path === null ? null : request()->getBaseUrl().$path,
            'indexUrl' => PageLinks::index(),
            'editUrl' => PageLinks::edit($page),
            'updateUrl' => PageLinks::update($page),
            'destroyUrl' => PageLinks::destroy($page),
            'childCreateUrl' => PageLinks::create($page->id),
            'parents' => array_map(static fn (PageNode $option): array => [
                'id' => $option->id,
                'title' => $option->title,
                'depth' => $option->depth,
            ], $parents->flatten()),
            'children' => array_map(static fn (PageNode $child): array => [
                'title' => $child->title,
                'path' => $child->path?->value,
                'status' => $child->status->value,
                'live' => $child->status === PageStatus::Published,
                'showUrl' => PageLinks::show($child->id),
            ], $node->children ?? []),
            'menuPlace' => self::menuPlace($pages, $node),
            'keepDays' => (int) config('made-cms-package.pages.keep_deleted_days', 30),
        ], $pages, $page);
    }

    /**
     * The place of the page among its siblings in the menu; null for a page outside the menu.
     *
     * @return array{position: int, total: int}|null
     */
    private static function menuPlace(PageTree $pages, ?PageNode $node): ?array
    {
        if ($node === null || ! $node->inMenu) {
            return null;
        }

        $siblings = $node->parentId === null ? $pages->roots : ($pages->find($node->parentId)->children ?? []);
        $inMenu = array_values(array_filter($siblings, static fn (PageNode $sibling): bool => $sibling->inMenu));
        $ids = array_map(static fn (PageNode $sibling): int => $sibling->id, $inMenu);

        return ['position' => (int) array_search($node->id, $ids, true) + 1, 'total' => count($ids)];
    }
}
