<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Actions\CheckScreenAccess;
use Made\CmsPackage\Access\Data\ScreenVisit;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;

it('opens a screen of one of the editor’s roles', function (): void {
    $editor = Editor::factory()->withRoles(Role::ContentManager, Role::SeoSpecialist)->make();

    expect(CheckScreenAccess::run(new ScreenVisit(editor: $editor, screen: Screen::Redirects)))->toBeTrue();
});

it('keeps a screen of another role closed', function (): void {
    $editor = Editor::factory()->withRoles(Role::ContentManager)->make();

    expect(CheckScreenAccess::run(new ScreenVisit(editor: $editor, screen: Screen::Redirects)))->toBeFalse();
});

it('opens every screen for an admin', function (Screen $screen): void {
    $editor = Editor::factory()->withRoles(Role::Admin)->make();

    expect(CheckScreenAccess::run(new ScreenVisit(editor: $editor, screen: $screen)))->toBeTrue();
})->with(Screen::cases());

it('opens no screen without an editor', function (): void {
    expect(CheckScreenAccess::run(new ScreenVisit(editor: null, screen: Screen::Pages)))->toBeFalse();
});
