<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Enums;

/**
 * What the launcher shows on a page of the host site.
 */
enum LauncherView: string
{
    case Hidden = 'hidden';
    case Tile = 'tile';
    case EditBar = 'edit_bar';
}
