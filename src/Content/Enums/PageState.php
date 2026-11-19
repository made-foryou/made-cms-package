<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * How a page stands for visitors, in one word: what the pill on the pages screen says.
 */
enum PageState: string
{
    case Live = 'live';
    case Hidden = 'hidden';
    case Draft = 'draft';

    /**
     * A draft wins from hidden, because no address reaches a page that was never published; hidden wins from live,
     * because nothing lists the page while its address still reaches it.
     */
    public static function of(PageStatus $status, bool $hidden): self
    {
        return match (true) {
            $status === PageStatus::Draft => self::Draft,
            $hidden => self::Hidden,
            default => self::Live,
        };
    }
}
