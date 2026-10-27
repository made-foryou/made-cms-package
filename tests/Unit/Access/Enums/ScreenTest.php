<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\Screen;

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
