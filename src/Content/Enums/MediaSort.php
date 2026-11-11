<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * The order of a list of the media library: the files it got last first, or by name from a to z.
 */
enum MediaSort: string
{
    case Newest = 'newest';
    case Name = 'name';
}
