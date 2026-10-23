<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

/**
 * Plain recovery codes, shown to the editor once. Only their hashes are stored.
 */
final readonly class RecoveryCodes
{
    /**
     * @param  list<string>  $codes
     */
    public function __construct(
        public array $codes,
    ) {}
}
