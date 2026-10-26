<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

/**
 * A code typed over from the mail, in the browser with this binding.
 */
final readonly class CodeLogin
{
    public function __construct(
        public string $email,
        public string $code,
        public string $binding,
    ) {}
}
