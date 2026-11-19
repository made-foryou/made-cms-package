<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\MeasureMeta;
use Made\CmsPackage\Content\Data\MetaLength;
use Made\CmsPackage\Content\Enums\MetaField;

it('says a description is missing when there is no text', function (?string $text): void {
    expect(MeasureMeta::run(MetaField::Description, $text))
        ->toEqual(new MetaLength(length: 0, limit: 155, missing: true, tooLong: false));
})->with([null, '', "  \n "]);

it('counts a text up to the limit as fitting', function (): void {
    expect(MeasureMeta::run(MetaField::Title, str_repeat('a', 60)))
        ->toEqual(new MetaLength(length: 60, limit: 60, missing: false, tooLong: false));
});

it('counts a text over the limit as too long', function (): void {
    expect(MeasureMeta::run(MetaField::Title, str_repeat('a', 61)))
        ->toEqual(new MetaLength(length: 61, limit: 60, missing: false, tooLong: true));
});

it('counts characters, not bytes, of the text as google gets it', function (): void {
    expect(MeasureMeta::run(MetaField::Title, "  Café  in\nDordrecht ")->length)->toBe(17);
});
