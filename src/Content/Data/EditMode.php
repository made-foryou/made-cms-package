<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * Whether the current request shows the site in the edit mode of the CMS: the working copy (the draft, else the
 * published version) instead of the published strips.
 *
 * Bound per request (scoped) and off by default; the Http layer switches it on for an editor who may edit pages.
 */
final readonly class EditMode
{
    private function __construct(public bool $active) {}

    public static function off(): self
    {
        return new self(false);
    }

    public static function on(): self
    {
        return new self(true);
    }
}
