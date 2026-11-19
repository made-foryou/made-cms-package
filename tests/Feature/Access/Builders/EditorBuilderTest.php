<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;

it('finds an editor by e-mail address regardless of case and surrounding spaces', function (): void {
    $editor = Editor::factory()->create(['email' => 'menno@made-foryou.nl']);
    Editor::factory()->create();

    expect(Editor::query()->forEmail(' Menno@Made-ForYou.nl ')->sole()->is($editor))->toBeTrue();
});

it('finds nothing for an unknown e-mail address', function (): void {
    Editor::factory()->create(['email' => 'menno@made-foryou.nl']);

    expect(Editor::query()->forEmail('iemand@example.com')->exists())->toBeFalse();
});

it('finds the editor with exactly the typed address, in lower case and without surrounding spaces', function (): void {
    $editor = Editor::factory()->create(['email' => 'jose@example.com']);
    Editor::factory()->create();

    expect(Editor::query()->firstWithEmail(' Jose@Example.COM ')?->is($editor))->toBeTrue()
        ->and(Editor::query()->firstWithEmail('iemand@example.com'))->toBeNull();
});

it('finds no editor for an address that differs only in an accent or a sharp s, which a collation may ignore', function (string $typed): void {
    Editor::factory()->create(['email' => 'jose@example.com']);
    Editor::factory()->create(['email' => 'jesse@example.com']);

    expect(Editor::query()->firstWithEmail($typed))->toBeNull();
})->with([
    'accent in the domain' => 'jose@exämple.com',
    'accent before the @' => 'JOSÉ@example.com',
    'sharp s' => 'jeße@example.com',
]);

it('reads the stored roles back as roles', function (): void {
    $editor = Editor::factory()->withRoles(Role::SeoSpecialist, Role::Owner)->create();

    expect(Editor::query()->findOrFail($editor->id)->roles->all())->toBe([Role::SeoSpecialist, Role::Owner]);
});

it('finds the editors with a role', function (): void {
    $admin = Editor::factory()->withRoles(Role::ContentManager, Role::Admin)->create();
    Editor::factory()->withRoles(Role::ContentManager)->create();

    expect(Editor::query()->withRole(Role::Admin)->sole()->is($admin))->toBeTrue()
        ->and(Editor::query()->withRole(Role::ContentManager)->count())->toBe(2)
        ->and(Editor::query()->withRole(Role::Owner)->exists())->toBeFalse();
});

it('leaves one editor out', function (): void {
    $editor = Editor::factory()->create();
    $other = Editor::factory()->create();

    expect(Editor::query()->otherThan($editor)->sole()->is($other))->toBeTrue();
});
