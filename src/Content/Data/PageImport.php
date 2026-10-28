<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * The initial content of one page, read from resources/cms/pages/<page>.json in the host.
 */
final readonly class PageImport
{
    /**
     * @param  list<StripImport>  $strips
     */
    public function __construct(
        public string $path,
        public string $title,
        public array $strips,
    ) {}
}
