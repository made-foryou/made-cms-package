<?php

declare(strict_types=1);

namespace Made\CmsPackage\Access\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Access\Data\RoleAssignment;
use Made\CmsPackage\Access\Data\ScreenVisit;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Events\EditorRolesChanged;
use Made\CmsPackage\Access\Exceptions\RolesRejected;
use Made\CmsPackage\Access\Models\Editor;

/**
 * Replaces the roles of an editor. Whoever may open the users and roles screen changes roles, only an admin
 * gives or takes the admin role, and the last admin keeps it so the CMS never locks everyone out.
 */
final class AssignRoles
{
    use AsAction;

    public function handle(RoleAssignment $assignment): Editor
    {
        $editor = $assignment->editor;
        $roles = Role::ordered($assignment->roles);

        if ($roles === []) {
            throw RolesRejected::empty();
        }

        if (! CheckScreenAccess::run(new ScreenVisit(editor: $assignment->by, screen: Screen::Editors))) {
            throw RolesRejected::notAllowed();
        }

        $wasAdmin = $editor->roles->containsStrict(Role::Admin);
        $staysAdmin = in_array(Role::Admin, $roles, true);

        if ($wasAdmin !== $staysAdmin && ! $assignment->by->roles->containsStrict(Role::Admin)) {
            throw RolesRejected::notAllowed();
        }

        if ($wasAdmin && ! $staysAdmin && ! Editor::query()->withRole(Role::Admin)->otherThan($editor)->exists()) {
            throw RolesRejected::lastAdmin();
        }

        $previous = array_values($editor->roles->all());

        $editor->roles = collect($roles);
        $editor->save();

        EditorRolesChanged::dispatch($editor, $assignment->by, $previous);

        return $editor;
    }
}
