<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * What a page with the menu switch on has in the menu of the site.
 */
enum MenuPlaceReason: string
{
    /** It is in the menu of the site, at a place among the pages next to it. */
    case Position = 'position';

    /** It is a draft: it takes its place once it is published. */
    case Publish = 'publish';

    /** The menu of the site leaves out a page above it, and the pages under that along. */
    case Parent = 'parent';

    /** It is hidden from visitors, so it has no place while it is hidden. */
    case WhileHidden = 'while_hidden';
}
