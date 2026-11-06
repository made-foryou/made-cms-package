<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Enums\ScanDoubt;

it('stores every doubt as a string value', function (): void {
    expect(array_map(static fn (ScanDoubt $doubt): string => $doubt->value, ScanDoubt::cases()))->toBe([
        'generic_name',
        'contains_form',
        'decorative_image',
        'background_image',
        'icon_choice',
        'items_differ',
        'nested_list',
        'same_in_every_item',
    ]);
});

it('explains every doubt in Dutch and in English', function (string $locale): void {
    app()->setLocale($locale);

    foreach (ScanDoubt::cases() as $doubt) {
        expect($doubt->label())->not->toBe('')
            ->not->toContain('made-cms-package::');
    }
})->with(['nl', 'en']);

it('explains a doubt in the words of the editor', function (): void {
    expect(ScanDoubt::IconChoice->label())->toBe('Keuze geraden uit de iconen van de items.');
});
