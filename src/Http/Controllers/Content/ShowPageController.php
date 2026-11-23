<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Made\CmsPackage\Access\Actions\ListScreens;
use Made\CmsPackage\Content\Actions\DescribePageMeta;
use Made\CmsPackage\Content\Actions\DescribeShareImage;
use Made\CmsPackage\Content\Actions\FindMediaFolder;
use Made\CmsPackage\Content\Actions\ListMenuPages;
use Made\CmsPackage\Content\Actions\ListPageAttention;
use Made\CmsPackage\Content\Actions\ListPages;
use Made\CmsPackage\Content\Actions\ListParentOptions;
use Made\CmsPackage\Content\Actions\MakeFallbackTitle;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\PageTypes\PageTypes;
use Made\CmsPackage\Http\Launcher\Visitor;
use Made\CmsPackage\Http\Responses\PageCreatedResponse;
use Made\CmsPackage\Http\Responses\PageRedirect;
use Made\CmsPackage\Http\Responses\ShowPageResponse;
use Made\CmsPackage\Http\Session\MediaFolderMemory;

/**
 * One page: what it asks for attention, its basics, how Google sees it, how a shared link shows it, the pages under it
 * and removing it. Right after the wizard: that it is ready.
 */
final class ShowPageController
{
    public function __invoke(Request $request, Page $page, PageTypes $types): Response
    {
        $editor = Visitor::requireEditor($request);
        $screens = ListScreens::run($editor);
        $pages = ListPages::run();

        if (PageRedirect::justCreated($request, $page)) {
            return PageCreatedResponse::make($editor, $screens, $page, $pages);
        }

        return ShowPageResponse::make(
            editor: $editor,
            screens: $screens,
            page: $page,
            pages: $pages,
            parents: ListParentOptions::run($page),
            menu: ListMenuPages::run(),
            meta: DescribePageMeta::run($page),
            fallbackTitle: MakeFallbackTitle::run($page),
            metaSaved: PageRedirect::justSavedMeta($request, $page),
            share: DescribeShareImage::run($page),
            folder: FindMediaFolder::run(MediaFolderMemory::of($request)->id()),
            shareSaved: PageRedirect::justSavedShare($request, $page),
            attention: ListPageAttention::run($page),
            types: $types,
        );
    }
}
