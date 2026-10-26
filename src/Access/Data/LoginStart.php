<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

/**
 * The e-mail address typed in the first login step, with what the device remembers (if anything).
 */
final readonly class LoginStart
{
    public function __construct(
        public string $email,
        public ?KnownDevice $device,
    ) {}
}
