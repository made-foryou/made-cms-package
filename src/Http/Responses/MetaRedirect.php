<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Made\CmsPackage\Content\Data\MetaRoundStep;
use Made\CmsPackage\Content\Enums\MetaFilter;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Cms\MetaLinks;
use Made\CmsPackage\Http\Cms\Shell;

/**
 * Where the Meta-overzicht goes after saving a page: the next point of the round, a new round once it is through (the
 * points passed by, or the finished screen), or back to a page that was no point. Every way says what was saved.
 */
final class MetaRedirect
{
    public static function saved(Page $page, MetaRoundStep $step, ?MetaFilter $filter, int $done): RedirectResponse
    {
        $url = match (true) {
            ! $step->inRound => MetaLinks::screen($filter, $page->id, $done, panel: true),
            $step->next !== null => MetaLinks::screen($filter, $step->next, $done + ($step->settled ? 1 : 0), panel: true),
            default => MetaLinks::screen($filter, panel: true),
        };

        return redirect()->to($url)->with(Shell::NOTICE, ['text' => 'meta_saved', 'title' => $page->title]);
    }
}
