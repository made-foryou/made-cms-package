<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * What an address of the site answered a visit with.
 */
enum HitOutcome: string
{
    /** No page and no redirect: a dead door. */
    case NotFound = 'not_found';

    /** A redirect sent the visitor on. */
    case Redirected = 'redirected';

    /** A file of the package: sitemap.xml or robots.txt. */
    case Served = 'served';

    /** A page, counted for crawlers only: the views of people belong to the statistics of the site. */
    case Page = 'page';
}
