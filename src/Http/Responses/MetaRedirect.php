<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Made\CmsPackage\Content\Data\MetaRoundStep;
use Made\CmsPackage\Content\Enums\MetaFilter;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Cms\MetaLinks;

/**
 * Where the Meta-overzicht goes after saving a page: the next point of the round, a new round once it is through (the
 * points passed by, or the finished screen), or back to a page that was no point. Every way lands on the panel, which
 * says once what was saved, so the editor sees it where the editor goes on.
 */
final class MetaRedirect
{
    /** The flash that makes the panel say once which page it saved. */
    private const string SAVED = 'made-cms-package.meta_saved';

    public static function saved(Page $page, MetaRoundStep $step, ?MetaFilter $filter, int $done): RedirectResponse
    {
        $url = match (true) {
            ! $step->inRound => MetaLinks::screen($filter, $page->id, $done, panel: true),
            $step->next !== null => MetaLinks::screen($filter, $step->next, $done + ($step->settled ? 1 : 0), panel: true),
            default => MetaLinks::screen($filter, panel: true),
        };

        return redirect()->to($url)->with(self::SAVED, $page->title);
    }

    /**
     * The name of the page the panel just saved, once.
     */
    public static function justSaved(Request $request): ?string
    {
        $title = $request->hasSession() ? $request->session()->get(self::SAVED) : null;

        return is_string($title) ? $title : null;
    }
}
