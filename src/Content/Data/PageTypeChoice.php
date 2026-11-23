<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The type an editor chooses for a page: the key of a page type, or null for an ordinary page.
 */
final readonly class PageTypeChoice
{
    public function __construct(
        public ?string $key,
    ) {}
}
