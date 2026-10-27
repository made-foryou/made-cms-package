<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

use Made\CmsPackage\Access\Enums\Role;

/**
 * The details of a CMS account to create. Without a password the editor logs in with a code.
 */
final readonly class NewEditor
{
    /**
     * @param  list<Role>  $roles  at least one
     */
    public function __construct(
        public string $email,
        public string $name,
        public ?string $password,
        public array $roles,
    ) {}
}
