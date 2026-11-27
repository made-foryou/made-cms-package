<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Suggestions;

use Made\CmsPackage\Content\Data\DescriptionSuggestion;
use Made\CmsPackage\Content\Models\Page;

/**
 * Something that can propose a description in Google for a page: its own text now, an AI later (#98). A source only
 * reads; SuggestPageDescription asks the sources in their order and takes the first proposal that fits.
 */
interface DescriptionSource
{
    public function suggest(Page $page): ?DescriptionSuggestion;
}
