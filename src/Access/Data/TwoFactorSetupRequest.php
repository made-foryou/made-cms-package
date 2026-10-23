<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

use Made\CmsPackage\Access\Models\Editor;

/**
 * An editor who wants to add an authenticator app, and when (unix time) they last typed their password.
 */
final readonly class TwoFactorSetupRequest
{
    public function __construct(
        public Editor $editor,
        public ?int $passwordConfirmedAt,
    ) {}
}
