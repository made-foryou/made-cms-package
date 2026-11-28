<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * What made-cms:prune removed of the traffic on the addresses: the rows of counts, and the dead doors it forgot.
 */
final readonly class PrunedAddressHits
{
    public function __construct(
        public int $hits,
        public int $paths,
    ) {}
}
