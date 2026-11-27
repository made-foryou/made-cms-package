<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * How the title and the description of a page Google shows do (`PageMetaMeasure::standing()`). Every such page has
 * exactly one, so the counts of the Meta-overzicht add up to the pages Google shows.
 */
enum MetaStanding: string
{
    /** No description of its own: Google takes a piece of the page itself. Wins from a title that is too long. */
    case Missing = 'missing';

    /** The title or the description is longer than Google shows. */
    case Long = 'long';

    /** A description, and both fit. */
    case Ok = 'ok';
}
