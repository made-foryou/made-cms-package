<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Enums\Screen;

it('stores the role as a string value', function (Role $role, string $value): void {
    expect($role->value)->toBe($value);
})->with([
    [Role::Admin, 'admin'],
    [Role::Webmaster, 'webmaster'],
    [Role::ContentManager, 'content-manager'],
    [Role::SeoSpecialist, 'seo-specialist'],
    [Role::Owner, 'owner'],
]);

it('gives the admin every screen, also the ones added later', function (): void {
    expect(Role::Admin->screens())->toBe(Screen::cases());
});

it('gives each role the screens of its menu', function (Role $role, array $screens): void {
    expect($role->screens())->toBe($screens);
})->with([
    'webmaster' => [Role::Webmaster, [
        Screen::Pages, Screen::News, Screen::Media, Screen::Messages,
        Screen::Editors, Screen::Forms, Screen::Backups, Screen::Log, Screen::Domain,
    ]],
    'content manager' => [Role::ContentManager, [Screen::Pages, Screen::News, Screen::Media, Screen::Messages]],
    'seo specialist' => [Role::SeoSpecialist, [
        Screen::Pages, Screen::MetaTags, Screen::AltTexts, Screen::Links,
        Screen::Redirects, Screen::Sitemap, Screen::Keywords,
    ]],
    'owner' => [Role::Owner, [Screen::Pages, Screen::News, Screen::Messages]],
]);

it('labels the role in Dutch', function (Role $role, string $label): void {
    expect($role->label())->toBe($label);
})->with([
    [Role::Admin, 'Admin'],
    [Role::Webmaster, 'Beheerder'],
    [Role::ContentManager, 'Contentbeheerder'],
    [Role::SeoSpecialist, 'SEO-specialist'],
    [Role::Owner, 'Eigenaar'],
]);

it('labels and describes every role in Dutch and English', function (Role $role): void {
    expect($role->label())->not->toStartWith('made-cms-package::')
        ->and($role->description())->not->toStartWith('made-cms-package::');

    app()->setLocale('en');

    expect($role->label())->not->toStartWith('made-cms-package::')
        ->and($role->description())->not->toStartWith('made-cms-package::');
})->with(Role::cases());

it('labels the role in English', function (): void {
    app()->setLocale('en');

    expect(Role::Webmaster->label())->toBe('Webmaster')
        ->and(Role::ContentManager->label())->toBe('Content manager');
});

it('orders roles once each, in the order of the cases', function (): void {
    expect(Role::ordered([Role::Owner, Role::Admin, Role::Owner]))->toBe([Role::Admin, Role::Owner])
        ->and(Role::ordered([]))->toBe([]);
});
