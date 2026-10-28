<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Made\CmsPackage\Access\Enums\Screen;

/**
 * The menu of the CMS: the screens an editor opens, under the label of their group, in menu order.
 */
final class Menu
{
    /**
     * @param  list<Screen>  $screens  the screens of the editor, in menu order (ListScreens)
     * @return list<array{label: string, items: list<array{label: string, url: string, current: bool}>}>
     */
    public static function groups(array $screens, ?Screen $current = null): array
    {
        $groups = [];

        foreach ($screens as $screen) {
            $group = $screen->group()->value;

            $groups[$group] ??= ['label' => $screen->group()->label(), 'items' => []];
            $groups[$group]['items'][] = [
                'label' => $screen->label(),
                'url' => self::url($screen),
                'current' => $screen === $current,
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
}
