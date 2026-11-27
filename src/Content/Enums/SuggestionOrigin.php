<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * Where a proposed text comes from; a screen names it above the proposal.
 */
enum SuggestionOrigin: string
{
    /** The text of the page itself (PageTextDescriptionSource). */
    case PageText = 'page_text';
}
