<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * A page of the site as the browser rendered it, to scan: its address and its document as HTML.
 */
final readonly class ScannedPage
{
    public function __construct(
        public string $url,
        public string $html,
    ) {}
}
