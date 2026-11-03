<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * An address on the site, normalised to lowercase with one leading slash and no trailing slash: "/" for the home page.
 *
 * Lowercase makes "/Over-Ons" the same address as "/over-ons" on every database, whatever its collation.
 */
final readonly class PagePath
{
    public const string HOME = '/';

    private function __construct(public string $value) {}

    public static function fromString(string $path): self
    {
        $segments = array_filter(explode('/', mb_strtolower(trim($path))), static fn (string $segment): bool => $segment !== '');

        return new self(self::HOME.implode('/', $segments));
    }

    public function isHome(): bool
    {
        return $this->value === self::HOME;
    }

    /**
     * The address of a page with this slug under the page at this address: "/" and "team" give "/team".
     */
    public function child(string $slug): self
    {
        return self::fromString($this->value.'/'.$slug);
    }
}
