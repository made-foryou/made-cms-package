<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * What led to a sitemap being built: a change of a page, the command, the first request for it or an editor.
 */
enum SitemapCause: string
{
    case Published = 'published';
    case Updated = 'updated';
    case Moved = 'moved';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case Visibility = 'visibility';
    case Meta = 'meta';
    case Command = 'command';
    case Requested = 'requested';
    case Manual = 'manual';
}
