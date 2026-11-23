<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Attention\AttentionSource;

/**
 * Where the attention points of a page come from, in their order: what the pages know now; links and media later.
 */
final readonly class AttentionSources
{
    /**
     * @param  list<AttentionSource>  $sources
     */
    public function __construct(
        public array $sources,
    ) {}
}
