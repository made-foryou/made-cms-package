<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Models\PageVersion;

final class PrunePageVersions
{
    use AsAction;

    /**
     * Keeps the newest versions of every page, deleted pages too, removes the older ones and returns how many.
     *
     * The newest is the one visitors see, so at least one stays. It removes what lies before the oldest version it
     * keeps, so a page published while it prunes never loses its new version.
     *
     * @param  int|null  $keep  how many versions a page keeps; the configured keep_versions when left out
     */
    public function handle(?int $keep = null): int
    {
        $keep = max(1, $keep ?? config()->integer('made-cms-package.pages.keep_versions', 20));
        $pruned = 0;

        foreach (PageVersion::query()->pagesWithMoreThan($keep) as $page) {
            $kept = PageVersion::query()->ofPage($page)->newestFirst()->limit($keep)->pluck('number')->min();

            if (is_numeric($kept)) {
                $pruned += PageVersion::query()->ofPage($page)->before((int) $kept)->delete();
            }
        }

        return $pruned;
    }
}
