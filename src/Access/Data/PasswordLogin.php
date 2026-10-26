<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

/**
 * An address and password typed in the panel, and whether to remember this device.
 */
final readonly class PasswordLogin
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $remember,
    ) {}
}
