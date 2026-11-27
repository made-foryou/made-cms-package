<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\MeasureMeta;
use Made\CmsPackage\Content\Data\MetaLength;
use Made\CmsPackage\Content\Enums\MetaField;

it('says a description is missing when there is no text', function (?string $text): void {
    expect(MeasureMeta::run(MetaField::Description, $text))
        ->toEqual(new MetaLength(length: 0, limit: 155, missing: true, tooLong: false, nearLimit: false));
})->with([null, '', "  \n "]);

it('counts a text up to the limit as fitting', function (): void {
    expect(MeasureMeta::run(MetaField::Title, str_repeat('a', 60)))
        ->toEqual(new MetaLength(length: 60, limit: 60, missing: false, tooLong: false, nearLimit: true));
});

it('counts a text over the limit as too long', function (): void {
    expect(MeasureMeta::run(MetaField::Title, str_repeat('a', 61)))
        ->toEqual(new MetaLength(length: 61, limit: 60, missing: false, tooLong: true, nearLimit: false));
});

it('says a text that fits is near the limit above nine tenths of it', function (MetaField $field, int $length, bool $near): void {
    expect(MeasureMeta::run($field, str_repeat('a', $length))->nearLimit)->toBe($near);
})->with([
    'title of 54' => [MetaField::Title, 54, false],
    'title of 55' => [MetaField::Title, 55, true],
    'title of 60' => [MetaField::Title, 60, true],
    'title of 61, too long' => [MetaField::Title, 61, false],
    'description of 139' => [MetaField::Description, 139, false],
    'description of 140' => [MetaField::Description, 140, true],
    'description of 155' => [MetaField::Description, 155, true],
    'description of 156, too long' => [MetaField::Description, 156, false],
]);

it('counts characters, not bytes, of the text as google gets it', function (): void {
    expect(MeasureMeta::run(MetaField::Title, "  Café  in\nDordrecht ")->length)->toBe(17);
});
