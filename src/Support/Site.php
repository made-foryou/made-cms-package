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
     * An address a shared link or a crawler can open from anywhere: an http(s) address with a host as it is, an address
     * from the root of the domain ("/storage/foto.webp") from the scheme, host and port of the configured url, never
     * from the Host of the request. Null for anything else: an address relative to the page, one without a scheme
     * ("//cdn"), another scheme, or one with whitespace or control characters.
     */
    public static function absolute(string $url): ?string
    {
        if ($url === '' || preg_match('/[\s\x00-\x1F\x7F]/', $url) === 1 || str_starts_with($url, '//')) {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return self::origin().$url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true) && is_string($host) && $host !== '' ? $url : null;
    }

    /**
     * An absolute url for a relative path, built from the configured url so a forged Host header never ends up in a mail.
     */
    public static function url(string $path): string
    {
        $base = config('app.url');

        return rtrim(is_string($base) && $base !== '' ? $base : request()->getSchemeAndHttpHost(), '/').'/'.ltrim($path, '/');
    }

    /**
     * The scheme, host and port of the configured url, without its path.
     */
    private static function origin(): string
    {
        $base = config('app.url');
        $parts = is_string($base) ? parse_url($base) : false;

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return request()->getSchemeAndHttpHost();
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
