<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Events\StripMoved;
use Made\CmsPackage\Content\Exceptions\InvalidStripPosition;
use Made\CmsPackage\Content\Exceptions\StripNotFound;
use Made\CmsPackage\Content\Models\Page;

final class MoveStrip
{
    use AsAction;

    /**
     * Moves a strip of the draft to a new position; a strip already there stays untouched.
     *
     * @param  int  $position  0 for the first place, the number of strips minus one for the last
     *
     * @throws StripNotFound
     * @throws InvalidStripPosition
     */
    public function handle(Page $page, string $stripId, int $position): void
    {
        ChangeDraft::run($page, static function (PageStrips $strips, Page $page) use ($stripId, $position): void {
            $strip = $strips->find($stripId) ?? throw StripNotFound::id($page, $stripId);
            $from = (int) $strips->positionOf($stripId);

            if ($position < 0 || $position >= $strips->count()) {
                throw InvalidStripPosition::for($page, $position, $strips->count() - 1);
            }

            if ($position === $from) {
                return;
            }

            $page->draft_strips = $strips->move($stripId, $position)->toArray();

            StripMoved::dispatch($page, $strip, $from, $position);
        });
    }
}
