<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Enums\ScreenGroup;

it('lists the screens in menu order', function (): void {
    expect(array_map(fn (Screen $screen): string => $screen->value, Screen::cases()))->toBe([
        'pages',
        'news',
        'media',
        'messages',
        'meta-tags',
        'alt-texts',
        'links',
        'redirects',
        'sitemap',
        'keywords',
        'editors',
        'forms',
        'backups',
        'log',
        'domain',
    ]);
});

it('labels the screen in Dutch', function (Screen $screen, string $label): void {
    expect($screen->label())->toBe($label);
})->with([
    [Screen::Pages, 'Pagina’s'],
    [Screen::MetaTags, 'Meta-overzicht'],
    [Screen::Editors, 'Gebruikers en rollen'],
    [Screen::Domain, 'Domein en certificaat'],
]);

it('labels every screen in Dutch and English', function (Screen $screen): void {
    expect($screen->label())->not->toStartWith('made-cms-package::');

    app()->setLocale('en');

    expect($screen->label())->not->toStartWith('made-cms-package::');
})->with(Screen::cases());

it('labels the screen in English', function (): void {
    app()->setLocale('en');

    expect(Screen::Editors->label())->toBe('Users and roles');
});

it('puts every screen in its menu group', function (Screen $screen, ScreenGroup $group): void {
    expect($screen->group())->toBe($group);
})->with([
    [Screen::Pages, ScreenGroup::Site],
    [Screen::News, ScreenGroup::Collections],
    [Screen::Media, ScreenGroup::Collections],
    [Screen::Messages, ScreenGroup::Collections],
    [Screen::MetaTags, ScreenGroup::Findability],
    [Screen::AltTexts, ScreenGroup::Findability],
    [Screen::Links, ScreenGroup::Findability],
    [Screen::Redirects, ScreenGroup::Findability],
    [Screen::Sitemap, ScreenGroup::Findability],
    [Screen::Keywords, ScreenGroup::Findability],
    [Screen::Editors, ScreenGroup::Management],
    [Screen::Forms, ScreenGroup::Management],
    [Screen::Backups, ScreenGroup::Management],
    [Screen::Log, ScreenGroup::Management],
    [Screen::Domain, ScreenGroup::Management],
]);

it('keeps the screens of a group together, in the order of the groups', function (): void {
    $groups = array_map(fn (Screen $screen): ScreenGroup => $screen->group(), Screen::cases());

    expect(array_values(array_unique($groups, SORT_REGULAR)))->toBe(ScreenGroup::cases());
});
