<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Suggestions\DescriptionSource;

/**
 * Where a proposed description comes from, in the order they are asked: the text of the page now, an AI later.
 */
final readonly class DescriptionSources
{
    /**
     * @param  list<DescriptionSource>  $sources
     */
    public function __construct(
        public array $sources,
    ) {}
}
