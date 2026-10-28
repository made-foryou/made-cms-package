<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * One strip of a page file: its type and its content, checked against the strip type on import.
 */
final readonly class StripImport
{
    /**
     * @param  array<array-key, mixed>  $content
     */
    public function __construct(
        public string $type,
        public array $content = [],
    ) {}
}
