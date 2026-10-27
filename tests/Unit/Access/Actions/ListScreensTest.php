<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Actions\ListScreens;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;

it('lists the screens of the role', function (): void {
    $editor = Editor::factory()->withRoles(Role::Owner)->make();

    expect(ListScreens::run($editor))->toBe([Screen::Pages, Screen::News, Screen::Messages]);
});

it('combines the screens of every role once, in menu order', function (): void {
    $editor = Editor::factory()->withRoles(Role::SeoSpecialist, Role::Owner)->make();

    expect(ListScreens::run($editor))->toBe([
        Screen::Pages, Screen::News, Screen::Messages, Screen::MetaTags, Screen::AltTexts,
        Screen::Links, Screen::Redirects, Screen::Sitemap, Screen::Keywords,
    ]);
});

it('lists every screen for an admin', function (): void {
    $editor = Editor::factory()->withRoles(Role::Admin, Role::Owner)->make();

    expect(ListScreens::run($editor))->toBe(Screen::cases());
});

it('lists nothing for an editor without roles', function (): void {
    $editor = Editor::factory()->withRoles()->make();

    expect(ListScreens::run($editor))->toBe([]);
});
