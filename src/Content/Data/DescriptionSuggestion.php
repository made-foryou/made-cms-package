<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\SuggestionOrigin;

/**
 * A proposed description in Google for a page, and where it comes from, so a screen can say so.
 */
final readonly class DescriptionSuggestion
{
    public function __construct(
        public string $text,
        public SuggestionOrigin $origin,
    ) {}
}
