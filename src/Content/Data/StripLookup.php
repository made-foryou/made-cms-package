<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The strips the edit mode found on a page, by id: all a host page tells the CMS about a strip.
 */
final readonly class StripLookup
{
    /**
     * @param  list<int>  $ids
     */
    public function __construct(
        public array $ids,
    ) {}
}
