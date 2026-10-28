<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Made\CmsPackage\Content\Actions\ListStripTypes;
use Made\CmsPackage\Content\Data\Field;
use Made\CmsPackage\Content\Enums\FieldType;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;

beforeEach(function (): void {
    $this->content = useContentFolders();
});

afterEach(function (): void {
    File::deleteDirectory($this->content);
});

it('reads every strip type of the site, keyed and sorted by key', function (): void {
    $types = ListStripTypes::run();

    expect(array_keys($types))->toBe(['cijfers', 'hero'])
        ->and($types['hero']->label)->toBe('Hero')
        ->and($types['cijfers']->fields)->toEqual([
            new Field(name: 'items', type: FieldType::List, label: 'Cijfers', fields: [
                new Field(name: 'value', type: FieldType::Text, label: 'Waarde'),
                new Field(name: 'label', type: FieldType::Text, label: 'Omschrijving'),
            ]),
        ]);
});

it('has no strip types without a strips folder', function (): void {
    File::deleteDirectory($this->content.'/strips');

    expect(ListStripTypes::run())->toBe([]);
});

it('ignores files that are not json', function (): void {
    File::put($this->content.'/strips/README.md', 'De strooktypes van de site.');

    expect(array_keys(ListStripTypes::run()))->toBe(['cijfers', 'hero']);
});

it('names the strip type of a file that is not valid json', function (): void {
    File::put($this->content.'/strips/teams.json', '{"label": "Teams",');

    expect(fn () => ListStripTypes::run())->toThrow(InvalidStripType::class, 'Strip type [teams] is invalid: the file is not valid JSON');
});

it('refuses a definition that breaks a rule', function (): void {
    File::put($this->content.'/strips/teams.json', '{"label": "Teams", "fields": [{"name": "title", "type": "txt", "label": "Titel"}]}');

    expect(fn () => ListStripTypes::run())->toThrow(InvalidStripType::class, 'Strip type [teams] is invalid: field [title] has no known type');
});
