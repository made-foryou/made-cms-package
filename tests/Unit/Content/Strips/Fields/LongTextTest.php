<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Strips\Fields\LongText;

it('gives its running text as a paragraph per line, without empty lines and spaces around them', function (): void {
    expect(LongText::make('intro')->prose("  Wij trainen op  dinsdag.\n\n\r\nKom gerust   langs. \n"))
        ->toBe("Wij trainen op dinsdag.\nKom gerust langs.");
});

it('has no running text without text', function (mixed $value): void {
    expect(LongText::make('intro')->prose($value))->toBeNull();
})->with([null, '', " \n ", 12, [['tekst']]]);
