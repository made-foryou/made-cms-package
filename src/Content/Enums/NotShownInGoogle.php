<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * Why Google does not show a page now. A page Google shows has no reason (`of()` gives null).
 */
enum NotShownInGoogle: string
{
    /** Visitors do not see the page yet, so neither does Google. */
    case Draft = 'draft';

    /** The page is hidden from visitors, which makes it noindex whatever its switch says. */
    case Hidden = 'hidden';

    /** The switch "Mag Google hem tonen" is off. */
    case Noindex = 'noindex';

    /**
     * The one rule for whether Google shows a page (`CheckShownInGoogle`): a draft is the first reason, a hidden page
     * the next, so a screen names the reason an editor has to take away first.
     */
    public static function of(PageStatus $status, bool $hidden, bool $indexable): ?self
    {
        return match (true) {
            $status !== PageStatus::Published => self::Draft,
            $hidden => self::Hidden,
            ! $indexable => self::Noindex,
            default => null,
        };
    }
}
