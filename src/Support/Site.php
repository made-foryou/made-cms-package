<?php

declare(strict_types=1);

namespace Made\CmsPackage\Support;

/**
 * The host site as editors know it, for the panel, the edit bar and the login mail.
 */
final class Site
{
    /**
     * The application name, unless the host never changed Laravel's default.
     */
    public static function name(): string
    {
        $name = config('app.name');

        return is_string($name) && trim($name) !== '' && $name !== 'Laravel' ? $name : self::host();
    }

    /**
     * The site host, taken from the configured url rather than the request's Host header.
     */
    public static function host(): string
    {
        $url = config('app.url');
        $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;

        if (! is_string($host) || $host === '') {
            $host = request()->getHost();
        }

        return (string) preg_replace('/^www\./i', '', $host);
    }

    /**
     * An absolute url for a relative path, built from the configured url so a forged Host header never ends up in a mail.
     */
    public static function url(string $path): string
    {
        $base = config('app.url');

        return rtrim(is_string($base) && $base !== '' ? $base : request()->getSchemeAndHttpHost(), '/').'/'.ltrim($path, '/');
    }
}
