<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

use Made\CmsPackage\Access\Models\LoginCode;

/**
 * A freshly issued login code. The plain code and link token only live in memory so they can be sent to the editor.
 */
final readonly class IssuedLoginCode
{
    public function __construct(
        public LoginCode $loginCode,
        public string $code,
        public string $linkToken,
    ) {}
}
