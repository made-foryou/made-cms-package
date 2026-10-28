<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\ParseStripType;
use Made\CmsPackage\Content\Data\Field;
use Made\CmsPackage\Content\Data\StripType;
use Made\CmsPackage\Content\Enums\FieldType;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;

it('reads a strip type with its fields', function (): void {
    $type = ParseStripType::run('hero', [
        '$schema' => '../../../vendor/made-foryou/made-cms-package/resources/schema/strip.json',
        'label' => 'Hero',
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => 'Titel'],
            ['name' => 'buttons', 'type' => 'list', 'label' => 'Knoppen', 'fields' => [
                ['name' => 'link', 'type' => 'link', 'label' => 'Knop'],
            ]],
        ],
    ]);

    expect($type)->toEqual(new StripType(key: 'hero', label: 'Hero', fields: [
        new Field(name: 'title', type: FieldType::Text, label: 'Titel'),
        new Field(name: 'buttons', type: FieldType::List, label: 'Knoppen', fields: [
            new Field(name: 'link', type: FieldType::Link, label: 'Knop'),
        ]),
    ]));
});

it('accepts a strip type without fields', function (): void {
    expect(ParseStripType::run('contact-form', ['label' => 'Contactformulier', 'fields' => []])->fields)->toBe([]);
});

it('refuses a definition that breaks a rule', function (string $key, mixed $definition, string $message): void {
    expect(fn () => ParseStripType::run($key, $definition))->toThrow(InvalidStripType::class, $message);
})->with([
    'a key that is not kebab-case' => ['Over_Ons', ['label' => 'Over ons', 'fields' => []], 'Strip type [Over_Ons] is invalid: the key is not written in kebab-case'],
    'a key with a trailing newline' => ["hero\n", ['label' => 'Hero', 'fields' => []], 'the key is not written in kebab-case'],
    'a name with a trailing newline' => ['hero', ['label' => 'Hero', 'fields' => [['name' => "title\n", 'type' => 'text', 'label' => 'Titel']]], 'every field needs a name in snake_case'],
    'an unknown key in a field' => ['hero', ['label' => 'Hero', 'fields' => [['name' => 'title', 'type' => 'text', 'label' => 'Titel', 'help' => 'x']]], 'field [title] has an unknown key [help]'],
    'a name used twice in a list' => ['hero', ['label' => 'Hero', 'fields' => [['name' => 'items', 'type' => 'list', 'label' => 'Items', 'fields' => [['name' => 'text', 'type' => 'text', 'label' => 'Tekst'], ['name' => 'text', 'type' => 'textarea', 'label' => 'Tekst']]]]], 'field [items.text] appears twice'],
    'a path as key' => ['../evil', ['label' => 'Evil', 'fields' => []], 'the key is not written in kebab-case'],
    'not an object' => ['hero', 'Hero', 'Strip type [hero] is invalid: the definition is not an object'],
    'an unknown key' => ['hero', ['label' => 'Hero', 'fields' => [], 'feilds' => []], 'the definition has an unknown key [feilds]'],
    'no label' => ['hero', ['fields' => []], 'the definition needs a label'],
    'no fields' => ['hero', ['label' => 'Hero'], 'the definition needs a list of fields'],
    'a name that is not snake_case' => ['hero', ['label' => 'Hero', 'fields' => [
        ['name' => 'Title', 'type' => 'text', 'label' => 'Titel'],
    ]], 'every field needs a name in snake_case, found [Title]'],
    'an unknown field type' => ['hero', ['label' => 'Hero', 'fields' => [
        ['name' => 'title', 'type' => 'txt', 'label' => 'Titel'],
    ]], 'field [title] has no known type (text, textarea, image, link, list)'],
    'a field without label' => ['hero', ['label' => 'Hero', 'fields' => [
        ['name' => 'title', 'type' => 'text'],
    ]], 'field [title] needs a label'],
    'a name used twice' => ['hero', ['label' => 'Hero', 'fields' => [
        ['name' => 'title', 'type' => 'text', 'label' => 'Titel'],
        ['name' => 'title', 'type' => 'textarea', 'label' => 'Titel'],
    ]], 'field [title] appears twice'],
    'fields on a text' => ['hero', ['label' => 'Hero', 'fields' => [
        ['name' => 'title', 'type' => 'text', 'label' => 'Titel', 'fields' => []],
    ]], 'field [title] only has fields when it is a list'],
    'a list without fields' => ['hero', ['label' => 'Hero', 'fields' => [
        ['name' => 'items', 'type' => 'list', 'label' => 'Items', 'fields' => []],
    ]], 'field [items] needs at least one field'],
    'a list inside a list' => ['hero', ['label' => 'Hero', 'fields' => [
        ['name' => 'items', 'type' => 'list', 'label' => 'Items', 'fields' => [
            ['name' => 'sub', 'type' => 'list', 'label' => 'Sub', 'fields' => [
                ['name' => 'text', 'type' => 'text', 'label' => 'Tekst'],
            ]],
        ]],
    ]], 'field [items.sub] is a list inside a list'],
]);
