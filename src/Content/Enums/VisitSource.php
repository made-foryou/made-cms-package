<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * Where a visit of an address came from, as the user agent and the referrer tell it.
 */
enum VisitSource: string
{
    /** A person who found it in Google. */
    case Google = 'google';

    /** A person who found it in another search engine. */
    case SearchEngine = 'search_engine';

    /** A person who followed a link on the site itself. */
    case Internal = 'internal';

    /** A person who followed a link on another site. */
    case External = 'external';

    /** A person without a referrer: typed, a bookmark, a mail or an app. */
    case Direct = 'direct';

    /** The crawler of Google, as its user agent calls itself. */
    case Googlebot = 'googlebot';

    /** Any other crawler or script. */
    case Bot = 'bot';

    public function isCrawler(): bool
    {
        return in_array($this, self::crawlers(), true);
    }

    /**
     * @return list<self>
     */
    public static function crawlers(): array
    {
        return [self::Googlebot, self::Bot];
    }

    /**
     * @return list<self>
     */
    public static function people(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $source): bool => ! $source->isCrawler()));
    }
}
