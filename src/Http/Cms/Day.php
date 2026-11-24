<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Carbon\CarbonImmutable;

/**
 * A day as a screen of the CMS says it: short in a list ("vandaag", "gisteren", "20 okt" in this year, "12 mrt 2024"
 * before), in full in a sentence ("20 oktober", "12 maart 2024").
 */
final class Day
{
    public static function of(CarbonImmutable $moment, string $locale): string
    {
        // The short month of some languages ends with a dot ("okt."), which reads as the end of a sentence in a list.
        return self::relative($moment, $locale)
            ?? str_replace('.', '', $moment->settings(['locale' => $locale])->isoFormat($moment->isCurrentYear() ? 'D MMM' : 'D MMM YYYY'));
    }

    public static function long(CarbonImmutable $moment, string $locale): string
    {
        return self::relative($moment, $locale)
            ?? $moment->settings(['locale' => $locale])->isoFormat($moment->isCurrentYear() ? 'D MMMM' : 'D MMMM YYYY');
    }

    private static function relative(CarbonImmutable $moment, string $locale): ?string
    {
        return match (true) {
            $moment->isToday() => (string) __('made-cms-package::cms.dates.today', [], $locale),
            $moment->isYesterday() => (string) __('made-cms-package::cms.dates.yesterday', [], $locale),
            default => null,
        };
    }
}
