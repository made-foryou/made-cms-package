<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Cms\Shell;

/**
 * Where the pages screen goes after a change, with the notice the next page shows.
 */
final class PageRedirect
{
    /** The flash that makes the page show once that it is ready, right after the wizard. */
    private const string CREATED = 'made-cms-package.page_created';

    public static function created(Page $page): RedirectResponse
    {
        return redirect()->route('made-cms-package.pages.show', ['page' => $page->id])->with(self::CREATED, $page->id);
    }

    public static function justCreated(Request $request, Page $page): bool
    {
        return $request->hasSession() && $request->session()->get(self::CREATED) === $page->id;
    }

    public static function saved(Page $page): RedirectResponse
    {
        return redirect()->route('made-cms-package.pages.show', ['page' => $page->id])
            ->with(Shell::NOTICE, ['text' => 'saved', 'title' => $page->title]);
    }

    public static function deleted(Page $page): RedirectResponse
    {
        return redirect()->route('made-cms-package.screens.pages')
            ->with(Shell::NOTICE, ['text' => 'deleted', 'title' => $page->title, 'restore' => $page->id]);
    }

    public static function restored(Page $page): RedirectResponse
    {
        return redirect()->route('made-cms-package.screens.pages')
            ->with(Shell::NOTICE, ['text' => 'restored', 'title' => $page->title]);
    }
}
