<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

use Made\CmsPackage\Access\Enums\LoginMethod;

/**
 * What a device remembers about the editor who last logged in on it: never the plain e-mail address.
 */
final readonly class KnownDevice
{
    public function __construct(
        public string $emailHash,
        public ?string $firstName,
        public ?LoginMethod $method,
    ) {}
}
