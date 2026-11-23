<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Carbon\CarbonImmutable;

/**
 * A day as a screen of the CMS says it, short: "vandaag", "gisteren", "20 okt" in this year, "12 mrt 2024" before.
 */
final class Day
{
    public static function of(CarbonImmutable $moment, string $locale): string
    {
        if ($moment->isToday()) {
            return (string) __('made-cms-package::cms.dates.today', [], $locale);
        }

        if ($moment->isYesterday()) {
            return (string) __('made-cms-package::cms.dates.yesterday', [], $locale);
        }

        $format = $moment->isCurrentYear() ? 'D MMM' : 'D MMM YYYY';

        // The short month of some languages ends with a dot ("okt."), which reads as the end of a sentence in a list.
        return str_replace('.', '', $moment->settings(['locale' => $locale])->isoFormat($format));
    }
}
