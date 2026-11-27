<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * Where an open page stands in the round of the Meta-overzicht: "Pagina 3 van 10".
 */
final readonly class MetaRoundPlace
{
    /**
     * @param  int  $position  the points put in order this round, those passed by and this one
     * @param  int  $total  the points put in order this round and those that are left
     * @param  int  $done  the points put in order this round
     * @param  int|null  $next  the next point in menu order, null when this is the last
     */
    public function __construct(
        public int $position,
        public int $total,
        public int $done,
        public ?int $next,
    ) {}

    /**
     * The points that are left, this one included.
     */
    public function left(): int
    {
        return $this->total - $this->done;
    }
}
