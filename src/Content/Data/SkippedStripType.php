<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Made\CmsPackage\Content\Enums\StripTypeSkip;

/**
 * A strip type of the scan that got no class, and why.
 */
final readonly class SkippedStripType
{
    /**
     * @param  string  $class  the class it would have been
     * @param  string|null  $detail  what the catalogue said about a definition it refuses
     */
    public function __construct(
        public string $key,
        public string $class,
        public StripTypeSkip $reason,
        public ?string $detail = null,
    ) {}
}
