<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\Response;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Data\PageTree;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Cms\PageLinks;
use Made\CmsPackage\Http\Cms\Shell;

/**
 * The end of the wizard (design 15.6): the page is ready as an empty draft, and the way to fill it.
 */
final class PageCreatedResponse
{
    /**
     * @param  list<Screen>  $screens  the screens of the editor, in menu order (ListScreens)
     * @param  PageTree  $pages  every page of the site
     */
    public static function make(Editor $editor, array $screens, Page $page, PageTree $pages): Response
    {
        $parent = $page->parent_id === null ? null : $pages->find($page->parent_id);
        $t = static fn (string $key, array $replace = []): string => (string) __('made-cms-package::pages.created.'.$key, $replace);

        return Shell::respond('made-cms-package-launcher::cms.pages.created', $editor, $screens, Screen::Pages, [
            'title' => $page->title,
            'text' => $t('text', [
                'path' => (string) $page->route?->path,
                'place' => $parent === null ? $t('root') : $t('under', ['parent' => $parent->title]),
                'menu' => $page->in_menu ? $t('in_menu') : $t('hidden'),
            ]),
            'indexUrl' => PageLinks::index(),
            'showUrl' => PageLinks::show($page),
            'editUrl' => PageLinks::edit($page),
        ], $pages, $page);
    }
}
