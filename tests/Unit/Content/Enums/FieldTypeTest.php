<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Enums\FieldType;

it('stores the field type as a string value', function (FieldType $type, string $value): void {
    expect($type->value)->toBe($value);
})->with([
    [FieldType::Text, 'text'],
    [FieldType::Textarea, 'textarea'],
    [FieldType::Image, 'image'],
    [FieldType::Link, 'link'],
    [FieldType::List, 'list'],
]);

it('offers the same field types in the JSON Schema of a strip type', function (): void {
    $schema = json_decode((string) file_get_contents(__DIR__.'/../../../../resources/schema/strip.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($schema['$defs']['field']['properties']['type']['enum'])->toBe(array_column(FieldType::cases(), 'value'));
});
