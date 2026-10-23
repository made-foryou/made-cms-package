<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

use Made\CmsPackage\Access\Enums\LoginMethod;
use Made\CmsPackage\Access\Enums\LoginStatus;
use Made\CmsPackage\Access\Models\Editor;

/**
 * The outcome of a successful login step: logged in, or one step (the second factor) to go.
 */
final readonly class LoginResult
{
    public function __construct(
        public LoginStatus $status,
        public Editor $editor,
        public LoginMethod $method,
    ) {}
}
