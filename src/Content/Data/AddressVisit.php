<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\HitOutcome;

/**
 * A visit of an address the package answered, to count: the address within its route group, what it answered and
 * what the request tells about where it came from.
 */
final readonly class AddressVisit
{
    /**
     * @param  string  $prefix  the prefix of the route group without slashes, "" at the root
     * @param  bool  $editing  an editor in the edit mode or the preview, who is never counted
     */
    public function __construct(
        public string $prefix,
        public PagePath $path,
        public HitOutcome $outcome,
        public VisitClues $clues,
        public bool $editing = false,
    ) {}
}
