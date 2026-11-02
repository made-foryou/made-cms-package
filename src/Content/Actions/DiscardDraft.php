<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Events\DraftDiscarded;
use Made\CmsPackage\Content\Models\Page;

final class DiscardDraft
{
    use AsAction;

    /**
     * Throws the draft away, so the page is back at its published strips. Without a draft nothing happens.
     */
    public function handle(Page $page): Page
    {
        if ($page->draft_strips === null) {
            return $page;
        }

        $page->draft_strips = null;
        $page->save();

        DraftDiscarded::dispatch($page);

        return $page;
    }
}
