<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Exceptions\InvalidPagePath;

/**
 * An address on the site, normalised to lowercase with one leading slash and no trailing slash: "/" for the home page.
 *
 * Lowercase makes "/Over-Ons" the same address as "/over-ons" on every database, whatever its collation.
 */
final readonly class PagePath
{
    public const string HOME = '/';

    /**
     * The length of cms_routes.path, in characters as MySQL and PostgreSQL count a varchar.
     */
    public const int MAX_LENGTH = 255;

    private function __construct(public string $value) {}

    /**
     * No address of a page holds a control character or bytes that are no UTF-8 (a slug never does), and PostgreSQL
     * refuses both in a query, so such an address is refused before anything looks it up.
     *
     * @throws InvalidPagePath
     */
    public static function fromString(string $path): self
    {
        if (! mb_check_encoding($path, 'UTF-8') || preg_match('/\p{Cc}/u', $path) === 1) {
            throw InvalidPagePath::make();
        }

        $segments = array_filter(explode('/', mb_strtolower(trim($path))), static fn (string $segment): bool => $segment !== '');

        return new self(self::HOME.implode('/', $segments));
    }

    /**
     * Whether the address fits in cms_routes.path. A longer one can still be read (the address a deleted page would
     * get back), but no Action stores it.
     */
    public function fits(): bool
    {
        return mb_strlen($this->value) <= self::MAX_LENGTH;
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
