<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

/**
 * The second step of a password login: a code from the app or a recovery code for the editor whose password was right.
 */
final readonly class TwoFactorLogin
{
    public function __construct(
        public int $editorId,
        public ?string $code,
        public ?string $recoveryCode,
        public bool $remember,
    ) {}
}
