<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Data\PageTree;
use Made\CmsPackage\Content\Models\Page;

/**
 * The menu of the CMS: the screens an editor opens, under the label of their group, in menu order. With the pages of
 * the site, the pages screen becomes "All pages" followed by every page of the menu, indented under its parent.
 */
final class Menu
{
    /**
     * @param  list<Screen>  $screens  the screens of the editor, in menu order (ListScreens)
     * @param  PageTree|null  $pages  the pages of the site, for an editor who opens the pages screen
     * @param  Page|null  $page  the page whose screen is open
     * @return list<array{label: string, items: list<array{label: string, url: string, current: bool, depth: int}>}>
     */
    public static function groups(array $screens, ?Screen $current = null, ?PageTree $pages = null, ?Page $page = null): array
    {
        $groups = [];

        foreach ($screens as $screen) {
            $group = $screen->group()->value;

            $groups[$group] ??= ['label' => $screen->group()->label(), 'items' => []];

            if ($screen === Screen::Pages && $pages !== null) {
                array_push($groups[$group]['items'], ...self::pages($pages->inMenu(), $current === $screen, $page));

                continue;
            }

            $groups[$group]['items'][] = [
                'label' => $screen->label(),
                'url' => self::url($screen),
                'current' => $screen === $current,
                'depth' => 0,
            ];
        }

        return array_values($groups);
    }

    /**
     * The url of a screen without scheme and host, with the base path of apps in a subdirectory.
     */
    public static function url(Screen $screen): string
    {
        return request()->getBaseUrl().route('made-cms-package.screens.'.$screen->value, [], false);
    }

    /**
     * @return list<array{label: string, url: string, current: bool, depth: int}>
     */
    private static function pages(PageTree $pages, bool $onScreen, ?Page $page): array
    {
        $listed = $page !== null && $pages->find($page->id) !== null;

        return [
            [
                'label' => (string) __('made-cms-package::pages.menu.all'),
                'url' => PageLinks::index(),
                'current' => $onScreen && ! $listed,
                'depth' => 0,
            ],
            ...array_map(static fn (PageNode $node): array => [
                'label' => $node->title,
                'url' => PageLinks::show($node->id),
                'current' => $onScreen && $node->id === $page?->id,
                'depth' => $node->depth,
            ], $pages->flatten()),
        ];
    }
}
