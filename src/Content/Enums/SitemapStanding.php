<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

use Made\CmsPackage\Content\Data\GoogleRobotsRules;

/**
 * Whether a page is in the sitemap of a route group, and else why not.
 */
enum SitemapStanding: string
{
    case In = 'in';

    /** Never published, so visitors and Google do not see it. */
    case Draft = 'draft';

    /** Hidden from visitors. */
    case Hidden = 'hidden';

    /** "Mag Google hem tonen" is off. */
    case Noindex = 'noindex';

    /** "Staat in de sitemap" is off: the one reason the screen of the sitemap changes itself. */
    case Excluded = 'excluded';

    /** The page has no address. */
    case NoAddress = 'no_address';

    /** A rule of robots.txt keeps Googlebot away from its address. */
    case Blocked = 'blocked';

    /**
     * The one rule for the place of a page in the sitemap, in the order the sitemap reads a page (PageSitemapSource and
     * BuildSitemap): published, visible, indexable and its switch, then its address and robots.txt. Each page counts by
     * itself: a page under a hidden page can be in it.
     *
     * @param  string|null  $address  the address of the page with the prefix of the route group, null without one
     */
    public static function of(?NotShownInGoogle $notShown, bool $inSitemap, ?string $address, GoogleRobotsRules $rules): self
    {
        return match (true) {
            $notShown === NotShownInGoogle::Draft => self::Draft,
            $notShown === NotShownInGoogle::Hidden => self::Hidden,
            $notShown === NotShownInGoogle::Noindex => self::Noindex,
            ! $inSitemap => self::Excluded,
            $address === null => self::NoAddress,
            $rules->blocks($address) => self::Blocked,
            default => self::In,
        };
    }

    public function isIn(): bool
    {
        return $this === self::In;
    }

    /**
     * Whether the switch "in sitemap" decides it, so the screen of the sitemap offers it.
     */
    public function isChoice(): bool
    {
        return $this === self::In || $this === self::Excluded;
    }

    /**
     * Whether an editor keeps the published page out of the sitemap on purpose.
     */
    public function isDeliberate(): bool
    {
        return in_array($this, [self::Hidden, self::Noindex, self::Excluded], true);
    }
}
