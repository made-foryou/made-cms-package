<?php

declare(strict_types=1);

namespace Made\CmsPackage\Support;

/**
 * The language of everything an editor reads: the launcher, its messages and the login mail.
 */
final class Locale
{
    /**
     * The configured locale when the package ships it, English otherwise; never raw translation keys.
     */
    public static function launcher(): string
    {
        $locale = config()->string('made-cms-package.launcher.locale', 'nl');

        return app('translator')->hasForLocale('made-cms-package::launcher.title', $locale) ? $locale : 'en';
    }
}
