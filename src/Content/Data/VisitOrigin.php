<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\VisitSource;

/**
 * Where a visit came from: its source, and for a link on the site itself the address of the page that held it.
 */
final readonly class VisitOrigin
{
    public function __construct(
        public VisitSource $source,
        public ?PagePath $referrer = null,
    ) {}
}
