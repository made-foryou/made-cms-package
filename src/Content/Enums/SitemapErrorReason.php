<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * Why an address that belongs in the sitemap is left out of it.
 */
enum SitemapErrorReason: string
{
    /** The page has no address. */
    case NoAddress = 'no_address';

    /** A rule of robots.txt keeps Google away from the address. */
    case Blocked = 'blocked';

    /** The sitemap holds the most addresses or bytes Google reads in one sitemap. */
    case Full = 'full';
}
