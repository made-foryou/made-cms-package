<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Strips\StripType;

/**
 * A strip a visitor sees, with the type of the catalogue that renders it.
 */
final readonly class ShownStrip
{
    public function __construct(
        public Strip $strip,
        public StripType $type,
    ) {}
}
