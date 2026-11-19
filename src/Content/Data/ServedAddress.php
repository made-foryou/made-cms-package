<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The address through which the current request serves a page: its path under the prefix of the route group that
 * serves it ("/nl/over-ons"), relative to the url of the site.
 *
 * Bound per request (scoped) and empty by default; the page route fills it in for the page it serves, so the canonical
 * address of that page keeps the prefix of its group.
 */
final readonly class ServedAddress
{
    private function __construct(
        public ?int $pageId,
        public ?string $path,
    ) {}

    public static function none(): self
    {
        return new self(null, null);
    }

    public static function of(int $pageId, string $path): self
    {
        return new self($pageId, $path);
    }
}
