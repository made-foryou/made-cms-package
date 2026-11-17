<?php

declare(strict_types=1);

namespace Made\CmsPackage\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Where the migrations of the package go when a host publishes them (made-cms-package-migrations). A migration the
 * host has already, under any date, goes onto that file: publishing again skips it, and --force overwrites it where
 * it is, never as a second copy under a new date that migrate would run again. A migration the host does not have
 * yet gets the date of the moment, one second after the other in the order of the package, as Laravel dates a
 * published migration, so it runs after the migrations the host ran.
 */
final class PublishedMigrations
{
    private const string DATE = '/^\d{4}_\d{2}_\d{2}_\d{6}_/';

    /**
     * @param  string  $source  the folder of the migrations of the package
     * @param  string  $target  the folder of the migrations of the host
     * @return array<string, string> the path of each migration of the package => where it is published
     */
    public static function paths(string $source, string $target, CarbonInterface $now): array
    {
        $published = [];

        foreach (glob($target.'/*.php') ?: [] as $file) {
            // The first copy wins when the host has one twice.
            $published[self::name($file)] ??= $file;
        }

        $files = glob($source.'/*.php') ?: [];
        sort($files);
        $at = CarbonImmutable::instance($now);
        $paths = [];

        foreach ($files as $file) {
            $name = self::name($file);

            if (isset($published[$name])) {
                $paths[$file] = $published[$name];

                continue;
            }

            $at = $at->addSecond();
            $paths[$file] = $target.'/'.$at->format('Y_m_d_His').'_'.$name;
        }

        return $paths;
    }

    /**
     * The name of a migration without its date: "create_cms_media_table.php".
     */
    private static function name(string $file): string
    {
        return (string) preg_replace(self::DATE, '', basename($file));
    }
}
