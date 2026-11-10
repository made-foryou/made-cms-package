<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Closure;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Models\Page;

final class ChangeDraft
{
    use AsAction;

    /**
     * The one way to change the draft of a page: every Action that writes the draft, publishes it or throws it away
     * runs its change through here.
     *
     * In a transaction it reads the page again with its row locked, so changes at the same time follow each other
     * instead of overwriting each other, and a page loaded before (by the route) never brings an old draft back. The
     * page it gets takes the stored state, whatever it held unsaved. The change gets the working copy (the draft, else
     * the published strips) and the page, writes what changes on the page and returns what the Action gives back;
     * the page is saved before the commit. A draft that no longer changes anything (CompareDraft) falls away, so
     * draft_strips is null unless there is something to publish or discard. When the strips of a version changed, the
     * places of the files of the media library follow in the same transaction (SyncMediaUsage). Events it dispatches go out after the
     * commit (ShouldDispatchAfterCommit), and an exception rolls everything back.
     *
     * It throws what the change throws (so no @throws of its own: the callers declare theirs), and a
     * ModelNotFoundException when the page was deleted after it was loaded.
     *
     * @template TResult
     *
     * @param  Closure(PageStrips, Page): TResult  $change
     * @return TResult
     */
    public function handle(Page $page, Closure $change): mixed
    {
        return DB::transaction(static function () use ($page, $change): mixed {
            $stored = Page::query()->whereKey($page->getKey())->lockForUpdate()->firstOrFail();
            $page->setRawAttributes($stored->getAttributes(), sync: true);

            $result = $change(PageStrips::fromArray($page->draft_strips ?? $page->strips), $page);

            if ($page->draft_strips !== null && CompareDraft::run($page)->count() === 0) {
                $page->draft_strips = null;
            }

            $stripsChanged = $page->isDirty(['strips', 'draft_strips']);

            $page->save();

            if ($stripsChanged) {
                SyncMediaUsage::run($page);
            }

            return $result;
        });
    }
}
