<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\Response;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Data\PageTree;
use Made\CmsPackage\Content\PageTypes\PageType;
use Made\CmsPackage\Content\PageTypes\PageTypes;
use Made\CmsPackage\Http\Cms\PageLinks;
use Made\CmsPackage\Http\Cms\Shell;
use Made\CmsPackage\Support\Site;

/**
 * The wizard for a new page (design 15.3–15.5): its type when the site defines page types, its name and address,
 * then its place.
 */
final class NewPageResponse
{
    /**
     * @param  list<Screen>  $screens  the screens of the editor, in menu order (ListScreens)
     * @param  PageTree  $parents  the pages the new page may lie under
     * @param  int|null  $parentId  the parent chosen beforehand, from "Page below"
     * @param  PageTypes  $types  the page types of the site; without them the wizard does not ask the type
     */
    public static function make(Editor $editor, array $screens, PageTree $parents, ?int $parentId, PageTypes $types): Response
    {
        $options = $parents->flatten();
        $chosen = $parentId !== null && $parents->find($parentId) !== null ? $parentId : null;

        return Shell::respond('made-cms-package-launcher::cms.pages.create', $editor, $screens, Screen::Pages, [
            'storeUrl' => PageLinks::store(),
            'indexUrl' => PageLinks::index(),
            'host' => Site::host(),
            'chosen' => $chosen,
            'types' => array_map(static fn (PageType $type): array => [
                'key' => $type->key(),
                'label' => $type->label(),
                'description' => $type->description(),
            ], $types->all()),
            'parents' => array_map(static fn (PageNode $node): array => [
                'id' => $node->id,
                'title' => $node->title,
                // The address the new page gets under it, without its own slug: "" under the home page.
                'prefix' => $node->path === null || $node->path->isHome() ? '' : $node->path->value,
                'depth' => $node->depth,
            ], $options),
        ]);
    }
}
