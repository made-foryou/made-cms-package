<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * What saving a page in the Meta-overzicht did to the round (UpdatePageMetaInRound).
 */
final readonly class MetaRoundStep
{
    /**
     * @param  bool  $inRound  the page was a point of the round before it was saved
     * @param  bool  $settled  saving put the point in order
     * @param  int|null  $next  the next point after it in menu order; null at the end of the round or for a page that
     *                          was no point
     */
    public function __construct(
        public bool $inRound,
        public bool $settled,
        public ?int $next,
    ) {}
}
