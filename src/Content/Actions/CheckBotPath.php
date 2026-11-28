<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Exceptions\InvalidPagePath;
use Made\CmsPackage\Support\ConfiguredStrings;

/**
 * Whether bots look for this address, for software the site does not run: the address itself or one under it. Such
 * a dead door weighs little and is proposed to be ignored. The developer adds to them in `address_hits.bot_paths`.
 */
final class CheckBotPath
{
    use AsAction;

    public const array PATHS = [
        '/wp-admin',
        '/wp-login.php',
        '/wp-content',
        '/wp-includes',
        '/wp-json',
        '/wp-config.php',
        '/xmlrpc.php',
        '/.env',
        '/.git',
        '/phpmyadmin',
        '/cgi-bin',
        '/administrator',
    ];

    public function handle(PagePath $path): bool
    {
        foreach (self::paths() as $botPath) {
            if ($path->value === $botPath->value || str_starts_with($path->value, $botPath->value.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<PagePath>
     */
    private static function paths(): array
    {
        $paths = [];

        foreach ([...self::PATHS, ...ConfiguredStrings::of('made-cms-package.address_hits.bot_paths')] as $path) {
            try {
                $path = PagePath::fromString($path);
            } catch (InvalidPagePath) {
                continue;
            }

            if ($path->fits() && ! $path->isHome()) {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
