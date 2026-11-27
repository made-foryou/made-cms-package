<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Made\CmsPackage\Support\Site;

/**
 * How a result shows the address of a page: the address above a result in Google (the card "Hoe Google hem ziet" and
 * the Meta-overzicht) and the domain of a shared link.
 */
final class GoogleResult
{
    /**
     * The address above a result in Google: the host and the parts of the path, "ttvmerwestad.nl › teams".
     */
    public static function address(?string $canonical): string
    {
        $path = (string) parse_url((string) $canonical, PHP_URL_PATH);
        $parts = array_map(rawurldecode(...), array_filter(explode('/', $path), static fn (string $part): bool => $part !== ''));

        return implode(' › ', [self::host($canonical), ...$parts]);
    }

    /**
     * The host of the canonical address, "ttvmerwestad.nl", else the host of the site.
     */
    public static function host(?string $canonical): string
    {
        $host = parse_url((string) $canonical, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : Site::host();
    }
}
