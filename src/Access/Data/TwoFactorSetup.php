<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

/**
 * A new authenticator secret, to scan (otpauth url) or type over, waiting to be confirmed with a code.
 */
final readonly class TwoFactorSetup
{
    public function __construct(
        public string $secret,
        public string $otpauthUrl,
    ) {}
}
