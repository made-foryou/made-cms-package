<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * One strip as the edit mode outlines it: its id, its type and the label of that type.
 */
final readonly class StripDescription
{
    public function __construct(
        public int $id,
        public string $type,
        public string $label,
    ) {}
}
