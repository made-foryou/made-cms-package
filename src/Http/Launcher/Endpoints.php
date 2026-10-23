<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Launcher;

/**
 * The CMS urls the launcher script calls, without scheme and host and with the base path of apps in a subdirectory.
 */
final class Endpoints
{
    private const array ROUTES = [
        'session' => 'made-cms-package.session',
        'email' => 'made-cms-package.login.email',
        'code' => 'made-cms-package.login.code',
        'verify' => 'made-cms-package.login.code.verify',
        'logout' => 'made-cms-package.logout',
    ];

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return array_map(self::url(...), self::ROUTES);
    }

    public static function url(string $route): string
    {
        return request()->getBaseUrl().route($route, [], false);
    }
}
