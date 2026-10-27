<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;

final class EditorRolesChanged
{
    use Dispatchable;

    /**
     * @param  list<Role>  $previous  the roles before the change; the editor holds the new ones
     */
    public function __construct(
        public readonly Editor $editor,
        public readonly Editor $by,
        public readonly array $previous,
    ) {}
}
