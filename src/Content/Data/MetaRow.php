<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * A page in the Meta-overzicht: its name, its address and how it does in Google.
 */
final readonly class MetaRow
{
    public function __construct(
        public int $id,
        public string $name,
        public ?PagePath $path,
        public PageMetaMeasure $measure,
    ) {}
}
