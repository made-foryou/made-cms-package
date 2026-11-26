<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Made\CmsPackage\Content\Data\RestoredVersion;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Cms\Shell;

/**
 * Where the pages screen goes after a change, with the notice the next page shows.
 */
final class PageRedirect
{
    /** The card "Hoe Google hem ziet" on the screen of a page, where saving it and a refusal go back to. */
    public const string GOOGLE = 'made-cms-google';

    /** The card "Hoe je link gedeeld wordt" on the screen of a page, where saving it and a refusal go back to. */
    public const string SHARE = 'made-cms-share';

    /** The flash that makes the page show once that it is ready, right after the wizard. */
    private const string CREATED = 'made-cms-package.page_created';

    /** The flash that makes the card "Hoe Google hem ziet" say once that it is saved. */
    private const string META_SAVED = 'made-cms-package.page_meta_saved';

    /** The flash that makes the card "Hoe je link gedeeld wordt" say once that it is saved. */
    private const string SHARE_SAVED = 'made-cms-package.page_share_saved';

    /** The card "Wat er eerder gebeurde" on the screen of a page, where putting a version back goes back to. */
    public const string VERSIONS = 'made-cms-versies';

    /** The flash that makes the card "Wat er eerder gebeurde" say once what putting a version back did. */
    private const string VERSION_RESTORED = 'made-cms-package.page_version_restored';

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

    /**
     * Back to the card of the share image, which says it is saved.
     */
    public static function shareSaved(Page $page): RedirectResponse
    {
        return redirect()->route('made-cms-package.pages.show', ['page' => $page->id])
            ->withFragment(self::SHARE)
            ->with(self::SHARE_SAVED, $page->id);
    }

    public static function justSavedShare(Request $request, Page $page): bool
    {
        return $request->hasSession() && $request->session()->get(self::SHARE_SAVED) === $page->id;
    }

    /**
     * Back to the card, which says what putting the version back did and leads to the edit shell to look at it.
     */
    public static function versionRestored(Page $page, RestoredVersion $restored): RedirectResponse
    {
        return redirect()->route('made-cms-package.pages.show', ['page' => $page->id])
            ->withFragment(self::VERSIONS)
            ->with(self::VERSION_RESTORED, [
                'page' => $page->id,
                'number' => $restored->number,
                'replaced' => $restored->replacedDraft,
                'draft' => $restored->draft,
            ]);
    }

    public static function justRestoredVersion(Request $request, Page $page): ?RestoredVersion
    {
        $flash = $request->hasSession() ? $request->session()->get(self::VERSION_RESTORED) : null;

        if (! is_array($flash) || ($flash['page'] ?? null) !== $page->id || ! is_int($flash['number'] ?? null)) {
            return null;
        }

        return new RestoredVersion($flash['number'], ($flash['replaced'] ?? false) === true, ($flash['draft'] ?? false) === true);
    }

    public static function visibilityChanged(Page $page): RedirectResponse
    {
        return redirect()->route('made-cms-package.pages.show', ['page' => $page->id])
            ->with(Shell::NOTICE, ['text' => $page->hidden ? 'hidden' : 'shown', 'title' => $page->title]);
    }

    /**
     * Opens the copy of a page with a notice that names the page it was copied from.
     */
    public static function duplicated(Page $copy, Page $origin): RedirectResponse
    {
        return redirect()->route('made-cms-package.pages.show', ['page' => $copy->id])
            ->with(Shell::NOTICE, ['text' => 'duplicated', 'title' => $copy->title, 'origin' => $origin->title]);
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
