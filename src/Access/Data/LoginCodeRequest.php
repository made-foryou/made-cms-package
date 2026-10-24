<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

/**
 * A request for a login code in one browser. The binding is a secret of that browser's session:
 * only that browser can use the code and the mail link. The user agent only tells the editor in the mail where it was asked for.
 */
final readonly class LoginCodeRequest
{
    public function __construct(
        public string $email,
        public string $binding,
        public ?string $returnTo,
        public ?string $userAgent = null,
    ) {}
}
