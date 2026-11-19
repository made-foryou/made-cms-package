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
    /** The card "Hoe Google hem ziet" on the screen of a page, where saving it and a refusal go back to. */
    public const string GOOGLE = 'made-cms-google';

    /** The flash that makes the page show once that it is ready, right after the wizard. */
    private const string CREATED = 'made-cms-package.page_created';

    /** The flash that makes the card "Hoe Google hem ziet" say once that it is saved. */
    private const string META_SAVED = 'made-cms-package.page_meta_saved';

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

    /**
     * Back to the card, which says it is saved: the notice of the screen stands at the top, out of sight.
     */
    public static function metaSaved(Page $page): RedirectResponse
    {
        return redirect()->route('made-cms-package.pages.show', ['page' => $page->id])
            ->withFragment(self::GOOGLE)
            ->with(self::META_SAVED, $page->id);
    }

    public static function justSavedMeta(Request $request, Page $page): bool
    {
        return $request->hasSession() && $request->session()->get(self::META_SAVED) === $page->id;
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
