<?php

declare(strict_types=1);

namespace Made\CmsPackage\Support;

/**
 * Relative paths between files of the host, e.g. from a strip type file to the JSON Schema in the package.
 */
final class RelativePath
{
    /**
     * The path to $to as seen from the folder $from. Both are absolute; Windows separators are accepted.
     */
    public static function between(string $from, string $to): string
    {
        $from = self::parts($from);
        $to = self::parts($to);

        while ($from !== [] && $to !== [] && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        return str_repeat('../', count($from)).implode('/', $to);
    }

    /**
     * @return list<string>
     */
    private static function parts(string $path): array
    {
        return array_values(array_filter(
            explode('/', str_replace('\\', '/', $path)),
            fn (string $part): bool => $part !== '' && $part !== '.',
        ));
    }
}
