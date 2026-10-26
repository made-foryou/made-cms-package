<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

use Made\CmsPackage\Access\Enums\LoginMethod;

/**
 * How the editor may continue after the e-mail step. The same for every address, unless the device knows it.
 */
final readonly class LoginOptions
{
    /**
     * @param  list<LoginMethod>  $methods
     */
    public function __construct(
        public ?string $firstName,
        public ?LoginMethod $preferred,
        public array $methods,
    ) {}
}
