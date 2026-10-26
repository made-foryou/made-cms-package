<?php

declare(strict_types=1);

namespace Made\CmsPackage\Support;

/**
 * Roughly which device and browser sent a request, so the login mail can say where the code was asked for.
 * Only for recognition by the editor: a user agent is easy to fake and never decides anything.
 */
final class Device
{
    /**
     * Most specific first: an iPhone says "like Mac OS X", Edge and Chrome on iOS also say "Safari".
     */
    private const array DEVICES = [
        'iphone' => '/iPhone/',
        'ipad' => '/iPad/',
        'android' => '/Android/',
        'mac' => '/Macintosh|Mac OS X/',
        'windows' => '/Windows/',
        'linux' => '/Linux|X11/',
    ];

    private const array BROWSERS = [
        'Edge' => '/Edg(e|A|iOS)?\//',
        'Opera' => '/OPR\/|Opera/',
        'Firefox' => '/Firefox\/|FxiOS\//',
        'Chrome' => '/Chrome\/|CriOS\//',
        'Safari' => '/Safari\//',
    ];

    /**
     * @return array{device: string|null, browser: string|null}
     */
    public static function from(?string $userAgent): array
    {
        return [
            'device' => self::match(self::DEVICES, $userAgent),
            'browser' => self::match(self::BROWSERS, $userAgent),
        ];
    }

    /**
     * @param  array<string, string>  $patterns
     */
    private static function match(array $patterns, ?string $userAgent): ?string
    {
        foreach ($patterns as $name => $pattern) {
            if ($userAgent !== null && preg_match($pattern, $userAgent) === 1) {
                return $name;
            }
        }

        return null;
    }
}
