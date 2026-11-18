<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/** Where an editor lands after leaving the edit shell. */
enum EditExit: string
{
    case Site = 'site';
    case Pages = 'pages';
}
