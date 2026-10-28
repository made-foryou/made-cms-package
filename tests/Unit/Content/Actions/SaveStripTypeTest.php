<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Made\CmsPackage\Content\Actions\ListStripTypes;
use Made\CmsPackage\Content\Actions\SaveStripType;
use Made\CmsPackage\Content\Data\Field;
use Made\CmsPackage\Content\Data\StripType;
use Made\CmsPackage\Content\Enums\FieldType;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;

beforeEach(function (): void {
    $this->content = useContentFolders(empty: true);

    $this->teams = new StripType(key: 'teams', label: 'Teams', fields: [
        new Field(name: 'title', type: FieldType::Text, label: 'Titel'),
        new Field(name: 'teams', type: FieldType::List, label: 'Teams', fields: [
            new Field(name: 'name', type: FieldType::Text, label: 'Naam'),
            new Field(name: 'results', type: FieldType::Link, label: 'Uitslagen'),
        ]),
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->content);
});

it('writes the strip type to its file in the strips folder', function (): void {
    $file = SaveStripType::run($this->teams);

    expect($file)->toBe($this->content.'/strips/teams.json')
        ->and(File::exists($file))->toBeTrue();
});

it('writes a file that reads back as the same strip type', function (): void {
    SaveStripType::run($this->teams);

    expect(ListStripTypes::run()['teams'])->toEqual($this->teams);
});

it('writes the keys in a fixed order, pretty printed and with a schema reference', function (): void {
    SaveStripType::run($this->teams);

    $contents = File::get($this->content.'/strips/teams.json');
    $definition = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

    expect(array_keys($definition))->toBe(['$schema', 'label', 'fields'])
        ->and(array_keys($definition['fields'][0]))->toBe(['name', 'type', 'label'])
        ->and(array_keys($definition['fields'][1]))->toBe(['name', 'type', 'label', 'fields'])
        ->and($definition['$schema'])->toEndWith('vendor/made-foryou/made-cms-package/resources/schema/strip.json')
        ->and($contents)->toContain("\n    \"label\": \"Teams\",\n")
        ->and($contents)->toEndWith("}\n");
});

it('creates the strips folder when the site has none yet', function (): void {
    File::deleteDirectory($this->content.'/strips');

    SaveStripType::run($this->teams);

    expect(File::exists($this->content.'/strips/teams.json'))->toBeTrue();
});

it('refuses a key that could leave the strips folder, without writing anything', function (): void {
    expect(fn () => SaveStripType::run(new StripType(key: '../evil', label: 'Evil', fields: [])))
        ->toThrow(InvalidStripType::class, 'the key is not written in kebab-case');

    expect(File::exists($this->content.'/evil.json'))->toBeFalse()
        ->and(File::files($this->content.'/strips'))->toBe([]);
});

it('refuses a strip type that would not read back', function (): void {
    $type = new StripType(key: 'cijfers', label: 'Cijfers', fields: [
        new Field(name: 'items', type: FieldType::List, label: 'Cijfers', fields: []),
    ]);

    expect(fn () => SaveStripType::run($type))->toThrow(InvalidStripType::class, 'field [items] needs at least one field');
});

it('refuses a key with a trailing newline and writes nothing', function (): void {
    expect(fn () => SaveStripType::run(new StripType(key: "hero\n", label: 'Hero', fields: [])))
        ->toThrow(InvalidStripType::class);

    expect(File::files($this->content.'/strips'))->toBe([]);
});
