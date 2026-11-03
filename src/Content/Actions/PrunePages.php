<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Models\Page;

final class PrunePages
{
    use AsAction;

    /**
     * Removes pages that were deleted longer ago than the days for good, and returns how many.
     *
     * A page is only deleted once the pages under it are, so a parent is never deleted later than its children:
     * pruning removes the pages without children first.
     *
     * @param  int|null  $days  how long a deleted page stays; the configured keep_deleted_days when left out
     */
    public function handle(?int $days = null): int
    {
        $days ??= (int) config('made-cms-package.pages.keep_deleted_days', 30);
        $remaining = Page::query()->trashedBefore(now()->toImmutable()->subDays($days))->get();

        return DB::transaction(function () use ($remaining): int {
            $pruned = 0;

            while ($remaining->isNotEmpty()) {
                $parents = $remaining->pluck('parent_id')->filter()->all();
                $leaves = $remaining->reject(static fn (Page $page): bool => in_array($page->id, $parents, true));

                $leaves->each(static fn (Page $page): ?bool => $page->forceDelete());
                $pruned += $leaves->count();
                $remaining = $remaining->diff($leaves);
            }

            return $pruned;
        });
    }
}
