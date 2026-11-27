<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MetaRoundStep;
use Made\CmsPackage\Content\Data\PageMetaChanges;
use Made\CmsPackage\Content\Models\Page;

final class UpdatePageMetaInRound
{
    use AsAction;

    /**
     * Saves the title and the description of a page from the Meta-overzicht (UpdatePageMeta, live at once) and says
     * what that did to the round: whether the page was a point, whether saving put it in order, and which point comes
     * next. A point that is still too long stays a point, so the round never counts it as done.
     */
    public function handle(Page $page, PageMetaChanges $changes): MetaRoundStep
    {
        $page->loadMissing('route');
        $wasPoint = MeasurePageMeta::run($page)->needsWork();

        UpdatePageMeta::run($page, $changes);

        return new MetaRoundStep(
            inRound: $wasPoint,
            settled: $wasPoint && ! MeasurePageMeta::run($page)->needsWork(),
            next: $wasPoint ? ListMetaRows::run()->nextInRound($page->id) : null,
        );
    }
}
