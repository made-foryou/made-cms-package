<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Sitemap;

/**
 * The path of a rule in robots.txt, read the way Google reads it: it matches a path from its start, a `*` stands for
 * any characters and a `$` at the end for the end of the address. Capitals count; an empty pattern matches nothing.
 */
final readonly class RobotsPattern
{
    public string $value;

    private ?string $expression;

    public function __construct(string $pattern)
    {
        $this->value = trim($pattern);
        $this->expression = $this->value === '' ? null : self::expression($this->value);
    }

    public function matches(string $path): bool
    {
        return $this->expression !== null && preg_match($this->expression, $path) === 1;
    }

    private static function expression(string $pattern): string
    {
        $anchored = str_ends_with($pattern, '$');
        $body = $anchored ? substr($pattern, 0, -1) : $pattern;
        $parts = array_map(static fn (string $part): string => preg_quote($part, '#'), explode('*', $body));

        return '#^'.implode('.*', $parts).($anchored ? '$' : '').'#';
    }
}
