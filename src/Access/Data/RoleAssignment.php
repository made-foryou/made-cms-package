<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Data;

use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;

/**
 * New roles for an editor, set by another editor (or by the editor themselves).
 */
final readonly class RoleAssignment
{
    /**
     * @param  list<Role>  $roles  the full set of roles the editor gets, at least one
     */
    public function __construct(
        public Editor $by,
        public Editor $editor,
        public array $roles,
    ) {}
}
