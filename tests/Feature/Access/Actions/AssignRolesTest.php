<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Access\Actions\AssignRoles;
use Made\CmsPackage\Access\Data\RoleAssignment;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Events\EditorRolesChanged;
use Made\CmsPackage\Access\Exceptions\RolesRejected;
use Made\CmsPackage\Access\Models\Editor;

/**
 * @return list<Role>
 */
function storedRoles(Editor $editor): array
{
    return Editor::query()->findOrFail($editor->id)->roles->all();
}

it('lets a webmaster change the roles of an editor, stored once each and in order', function (): void {
    $webmaster = Editor::factory()->withRoles(Role::Webmaster)->create();
    $editor = Editor::factory()->withRoles(Role::ContentManager)->create();

    AssignRoles::run(new RoleAssignment(by: $webmaster, editor: $editor, roles: [Role::Owner, Role::SeoSpecialist, Role::Owner]));

    expect(storedRoles($editor))->toBe([Role::SeoSpecialist, Role::Owner]);
});

it('refuses an editor without a role', function (): void {
    $admin = Editor::factory()->withRoles(Role::Admin)->create();
    $editor = Editor::factory()->withRoles(Role::ContentManager)->create();

    expect(fn () => AssignRoles::run(new RoleAssignment(by: $admin, editor: $editor, roles: [])))
        ->toThrow(RolesRejected::class, RolesRejected::empty()->getMessage());

    expect(storedRoles($editor))->toBe([Role::ContentManager]);
});

it('refuses an editor who may not open the users and roles screen', function (Role $role): void {
    $by = Editor::factory()->withRoles($role)->create();
    $editor = Editor::factory()->withRoles(Role::ContentManager)->create();

    expect(fn () => AssignRoles::run(new RoleAssignment(by: $by, editor: $editor, roles: [Role::Owner])))
        ->toThrow(RolesRejected::class, RolesRejected::notAllowed()->getMessage());

    expect(storedRoles($editor))->toBe([Role::ContentManager]);
})->with([Role::ContentManager, Role::SeoSpecialist, Role::Owner]);

it('refuses a webmaster who gives the admin role', function (): void {
    $webmaster = Editor::factory()->withRoles(Role::Webmaster)->create();

    expect(fn () => AssignRoles::run(new RoleAssignment(by: $webmaster, editor: $webmaster, roles: [Role::Admin, Role::Webmaster])))
        ->toThrow(RolesRejected::class, RolesRejected::notAllowed()->getMessage());

    expect(storedRoles($webmaster))->toBe([Role::Webmaster]);
});

it('refuses a webmaster who takes the admin role away', function (): void {
    Editor::factory()->withRoles(Role::Admin)->create();
    $webmaster = Editor::factory()->withRoles(Role::Webmaster)->create();
    $admin = Editor::factory()->withRoles(Role::Admin)->create();

    expect(fn () => AssignRoles::run(new RoleAssignment(by: $webmaster, editor: $admin, roles: [Role::ContentManager])))
        ->toThrow(RolesRejected::class, RolesRejected::notAllowed()->getMessage());

    expect(storedRoles($admin))->toBe([Role::Admin]);
});

it('lets a webmaster change the other roles of an admin', function (): void {
    $webmaster = Editor::factory()->withRoles(Role::Webmaster)->create();
    $admin = Editor::factory()->withRoles(Role::Admin)->create();

    AssignRoles::run(new RoleAssignment(by: $webmaster, editor: $admin, roles: [Role::Admin, Role::Owner]));

    expect(storedRoles($admin))->toBe([Role::Admin, Role::Owner]);
});

it('lets an admin give and take the admin role', function (): void {
    $admin = Editor::factory()->withRoles(Role::Admin)->create();
    $editor = Editor::factory()->withRoles(Role::ContentManager)->create();

    AssignRoles::run(new RoleAssignment(by: $admin, editor: $editor, roles: [Role::Admin]));

    expect(storedRoles($editor))->toBe([Role::Admin]);

    AssignRoles::run(new RoleAssignment(by: $admin, editor: $editor, roles: [Role::ContentManager]));

    expect(storedRoles($editor))->toBe([Role::ContentManager]);
});

it('keeps the admin role on the last admin', function (): void {
    $admin = Editor::factory()->withRoles(Role::Admin)->create();

    expect(fn () => AssignRoles::run(new RoleAssignment(by: $admin, editor: $admin, roles: [Role::Webmaster])))
        ->toThrow(RolesRejected::class, RolesRejected::lastAdmin()->getMessage());

    expect(storedRoles($admin))->toBe([Role::Admin]);
});

it('lets an admin give up the admin role while another admin remains', function (): void {
    Editor::factory()->withRoles(Role::Admin)->create();
    $admin = Editor::factory()->withRoles(Role::Admin)->create();

    AssignRoles::run(new RoleAssignment(by: $admin, editor: $admin, roles: [Role::Webmaster]));

    expect(storedRoles($admin))->toBe([Role::Webmaster]);
});

it('dispatches an event with the previous roles and who changed them', function (): void {
    Event::fake([EditorRolesChanged::class]);

    $admin = Editor::factory()->withRoles(Role::Admin)->create();
    $editor = Editor::factory()->withRoles(Role::ContentManager, Role::Owner)->create();

    AssignRoles::run(new RoleAssignment(by: $admin, editor: $editor, roles: [Role::SeoSpecialist]));

    Event::assertDispatched(EditorRolesChanged::class, fn (EditorRolesChanged $event): bool => $event->editor->is($editor)
        && $event->by->is($admin)
        && $event->previous === [Role::ContentManager, Role::Owner]
        && $event->editor->roles->all() === [Role::SeoSpecialist]);
});

it('dispatches nothing when a change is refused', function (): void {
    Event::fake([EditorRolesChanged::class]);

    $admin = Editor::factory()->withRoles(Role::Admin)->create();

    expect(fn () => AssignRoles::run(new RoleAssignment(by: $admin, editor: $admin, roles: [Role::Owner])))
        ->toThrow(RolesRejected::class);

    Event::assertNotDispatched(EditorRolesChanged::class);
});
