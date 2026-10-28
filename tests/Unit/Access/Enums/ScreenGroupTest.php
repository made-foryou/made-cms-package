<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\ScreenGroup;

it('lists the groups in menu order', function (): void {
    expect(array_map(fn (ScreenGroup $group): string => $group->value, ScreenGroup::cases()))->toBe([
        'site',
        'collections',
        'findability',
        'management',
    ]);
});

it('labels the group in Dutch', function (ScreenGroup $group, string $label): void {
    expect($group->label())->toBe($label);
})->with([
    [ScreenGroup::Site, 'Je site'],
    [ScreenGroup::Collections, 'Verzamelingen'],
    [ScreenGroup::Findability, 'Vindbaarheid'],
    [ScreenGroup::Management, 'Beheer'],
]);

it('labels every group in Dutch and English', function (ScreenGroup $group): void {
    expect($group->label())->not->toStartWith('made-cms-package::');

    app()->setLocale('en');

    expect($group->label())->not->toStartWith('made-cms-package::');
})->with(ScreenGroup::cases());

it('labels the group in English', function (): void {
    app()->setLocale('en');

    expect(ScreenGroup::Site->label())->toBe('Your site');
});
