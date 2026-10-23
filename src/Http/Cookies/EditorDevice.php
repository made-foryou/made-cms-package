<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cookies;

use Illuminate\Http\Request;
use Made\CmsPackage\Access\Data\KnownDevice;
use Made\CmsPackage\Access\Enums\LoginMethod;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The encrypted cookie that marks a device of an editor who logged in to the CMS before.
 * It shows the launcher tile and lets the panel greet the editor who typed the same address.
 */
final class EditorDevice
{
    public const string NAME = 'made_cms_editor';

    private const int LIFETIME_MINUTES = 60 * 24 * 365;

    public static function remembered(Request $request): bool
    {
        // '1' is the value of the first launcher release: still remembered, but without a greeting.
        return $request->cookie(self::NAME) === '1' || self::read($request) instanceof KnownDevice;
    }

    public static function read(Request $request): ?KnownDevice
    {
        $value = $request->cookie(self::NAME);
        $data = is_string($value) ? json_decode($value, true) : null;

        if (! is_array($data) || ($data['v'] ?? null) !== 1 || ! is_string($data['e'] ?? null)) {
            return null;
        }

        return new KnownDevice(
            emailHash: $data['e'],
            firstName: is_string($data['n'] ?? null) ? $data['n'] : null,
            method: is_string($data['m'] ?? null) ? LoginMethod::tryFrom($data['m']) : null,
        );
    }

    public static function value(KnownDevice $device): string
    {
        return (string) json_encode([
            'v' => 1,
            'e' => $device->emailHash,
            'n' => $device->firstName,
            'm' => $device->method?->value,
        ]);
    }

    public static function make(KnownDevice $device): Cookie
    {
        return cookie(self::NAME, self::value($device), self::LIFETIME_MINUTES, secure: null, httpOnly: true, sameSite: 'lax');
    }
}
