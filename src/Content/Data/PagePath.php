<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * An address on the site, normalised to one leading slash and no trailing slash: "/" for the home page.
 */
final readonly class PagePath
{
    public const string HOME = '/';

    private function __construct(public string $value) {}

    public static function fromString(string $path): self
    {
        $segments = array_filter(explode('/', trim($path)), static fn (string $segment): bool => $segment !== '');

        return new self(self::HOME.implode('/', $segments));
    }

    public function isHome(): bool
    {
        return $this->value === self::HOME;
    }
}
