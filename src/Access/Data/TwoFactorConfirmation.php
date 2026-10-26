<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

use Made\CmsPackage\Access\Models\Editor;

/**
 * The first code from the authenticator app, proving the new secret was saved correctly.
 */
final readonly class TwoFactorConfirmation
{
    public function __construct(
        public Editor $editor,
        public string $code,
    ) {}
}
