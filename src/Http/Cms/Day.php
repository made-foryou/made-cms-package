<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Carbon\CarbonImmutable;

/**
 * A day as a screen of the CMS says it: short in a list ("vandaag", "gisteren", "morgen", "20 okt" in this year,
 * "12 mrt 2024" in another), in full in a sentence ("20 oktober", "12 maart 2024"), a moment as that day with its time
 * ("20 oktober, 14:12"), and a moment in a sentence ("op 14 november om 09:00", "morgen om 09:00").
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
        return self::relative($moment, $locale) ?? self::date($moment, $locale);
    }

    public static function moment(CarbonImmutable $moment, string $locale): string
    {
        return self::long($moment, $locale).', '.self::time($moment, $locale);
    }

    public static function at(CarbonImmutable $moment, string $locale): string
    {
        $relative = self::relative($moment, $locale);

        return $relative === null
            ? (string) __('made-cms-package::cms.dates.at', ['day' => self::date($moment, $locale), 'time' => self::time($moment, $locale)], $locale)
            : (string) __('made-cms-package::cms.dates.at_relative', ['day' => $relative, 'time' => self::time($moment, $locale)], $locale);
    }

    private static function date(CarbonImmutable $moment, string $locale): string
    {
        return $moment->settings(['locale' => $locale])->isoFormat($moment->isCurrentYear() ? 'D MMMM' : 'D MMMM YYYY');
    }

    private static function time(CarbonImmutable $moment, string $locale): string
    {
        return $moment->settings(['locale' => $locale])->isoFormat('LT');
    }

    private static function relative(CarbonImmutable $moment, string $locale): ?string
    {
        return match (true) {
            $moment->isToday() => (string) __('made-cms-package::cms.dates.today', [], $locale),
            $moment->isYesterday() => (string) __('made-cms-package::cms.dates.yesterday', [], $locale),
            $moment->isTomorrow() => (string) __('made-cms-package::cms.dates.tomorrow', [], $locale),
            default => null,
        };
    }
}
