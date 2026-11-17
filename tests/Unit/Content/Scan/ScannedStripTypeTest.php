<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\ScanField;
use Made\CmsPackage\Content\Data\ScanType;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;
use Made\CmsPackage\Content\Scan\ScannedStripType;
use Made\CmsPackage\Content\Strips\Fields\Choice;
use Made\CmsPackage\Content\Strips\Fields\Field;
use Made\CmsPackage\Content\Strips\Fields\Heading;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\StripTypes;

/**
 * @param  list<ScanField>  $fields
 */
function scannedType(array $fields, string $key = 'over-ons', string $label = 'Over ons'): ScannedStripType
{
    return ScannedStripType::from(new ScanType($key, $label, null, 'fingerprint', 1, $fields));
}

/**
 * The fields of a proposal as "key: type", with a star when required and the fields of a list behind it.
 *
 * @param  list<Field>  $fields
 * @return list<string|array<string, list<string|array<string, list<string>>>>>
 */
function proposedFields(array $fields): array
{
    return array_map(static function (Field $field): string|array {
        $line = $field->getKey().': '.$field->type().($field->isRequired() ? '*' : '');

        return $field instanceof Items ? [$line => proposedFields($field->getFields())] : $line;
    }, $fields);
}

it('proposes a strip type with the key, label and fields of the scan', function (): void {
    $type = scannedType([
        new ScanField('kop', 'text', 'h2'),
        new ScanField('tekst', 'long_text', 'p', required: false),
        new ScanField('inhoud', 'rich_text', 'div'),
        new ScanField('knop', 'button', 'a'),
        new ScanField('link', 'link', 'a:nth-of-type(2)', required: false),
    ]);

    expect($type->key())->toBe('over-ons')
        ->and($type->label())->toBe('Over ons')
        ->and($type->leftOut())->toBe([])
        ->and(proposedFields($type->fields()))->toBe([
            'kop: text*',
            'tekst: long_text',
            'inhoud: rich_text*',
            'knop: button*',
            'link: link',
        ]);
});

it('proposes a list with its fields, the most items as max and a min only above one', function (): void {
    $type = scannedType([
        new ScanField('items', 'items', 'ul', item: 'li', min: 1, max: 3, fields: [
            new ScanField('kop', 'text', 'h3'),
            new ScanField('tekst', 'long_text', 'p', required: false),
        ]),
        new ScanField('cijfers', 'items', 'div', required: false, item: 'div', min: 2, max: 4, fields: [
            new ScanField('waarde', 'text', 'span'),
        ]),
        new ScanField('open', 'items', 'ol', item: 'li', fields: [new ScanField('regel', 'text', '')]),
    ]);

    [$items, $cijfers, $open] = $type->fields();

    expect(proposedFields($type->fields()))->toBe([
        ['items: items*' => ['kop: text*', 'tekst: long_text']],
        ['cijfers: items' => ['waarde: text*']],
        ['open: items*' => ['regel: text*']],
    ])
        ->and($items)->toBeInstanceOf(Items::class)
        ->and([$items->getMin(), $items->getMax()])->toBe([null, 3])
        ->and([$cijfers->getMin(), $cijfers->getMax()])->toBe([2, 4])
        ->and([$open->getMin(), $open->getMax()])->toBe([null, null]);
});

it('proposes a heading at the level the scan found, and at the default level without one', function (): void {
    $type = scannedType([
        new ScanField('kop', 'heading', 'h1', tag: 'h1'),
        new ScanField('kop_2', 'heading', 'h2', required: false),
        new ScanField('kaarten', 'items', 'div', item: 'div', min: 1, max: 2, fields: [new ScanField('titel', 'heading', 'h4', tag: 'h4')]),
    ]);

    [$main, $sub, $cards] = $type->fields();

    expect(proposedFields($type->fields()))->toBe(['kop: heading*', 'kop_2: heading', ['kaarten: items*' => ['titel: heading*']]])
        ->and($main)->toBeInstanceOf(Heading::class)
        ->and([$main->getTag(), $sub->getTag(), $cards->getFields()[0]->getTag()])->toBe(['h1', 'h2', 'h4'])
        ->and($main->getTags())->toBe(Heading::TAGS);
});

it('gives the options of a choice a label to translate', function (): void {
    $type = scannedType([
        new ScanField('icoon', 'choice', 'svg', options: ['users', 'map-pin', 'building2', 'users']),
        new ScanField('aantal', 'choice', 'span', options: ['0', '1', '2']),
    ]);

    [$icon, $count] = $type->fields();

    expect($icon)->toBeInstanceOf(Choice::class)
        ->and($icon->getOptions())->toBe(['users' => 'Users', 'map-pin' => 'Map pin', 'building2' => 'Building2'])
        ->and($count->getOptions())->toBe(['0', '1', '2']);
});

it('leaves out images until the image field exists, and a list that holds nothing else', function (): void {
    $type = scannedType([
        new ScanField('afbeelding', 'image', 'img'),
        new ScanField('kop', 'text', 'h2'),
        new ScanField('fotos', 'items', 'div', item: 'figure', min: 1, max: 4, fields: [new ScanField('foto', 'image', 'img')]),
        new ScanField('kaarten', 'items', 'div', item: 'article', min: 1, max: 2, fields: [
            new ScanField('afbeelding', 'image', 'img'),
            new ScanField('titel', 'text', 'h3'),
        ]),
    ]);

    expect(proposedFields($type->fields()))->toBe(['kop: text*', ['kaarten: items*' => ['titel: text*']]])
        ->and($type->leftOut())->toBe(['afbeelding', 'fotos.foto', 'kaarten.afbeelding']);
});

it('refuses a field type the package has no field for', function (): void {
    scannedType([new ScanField('kaart', 'map', 'iframe')]);
})->throws(InvalidStripType::class, 'field [kaart] has an unknown type [map]');

it('is checked by the rules of the catalogue', function (ScanType $scanned, string $message): void {
    expect(static fn (): StripTypes => new StripTypes([ScannedStripType::from($scanned)]))
        ->toThrow(InvalidStripType::class, $message);
})->with([
    'key' => [new ScanType('Over ons', 'Over ons', null, 'f', 1, [new ScanField('kop', 'text', 'h2')]), 'invalid key [Over ons]'],
    'field key' => [new ScanType('hero', 'Hero', null, 'f', 1, [new ScanField('Kop', 'text', 'h2')]), 'invalid field key [Kop]'],
    'reserved field' => [new ScanType('hero', 'Hero', null, 'f', 1, [new ScanField('id', 'text', 'h2')]), 'reserved field [id]'],
    'field twice' => [new ScanType('hero', 'Hero', null, 'f', 1, [new ScanField('kop', 'text', 'h2'), new ScanField('kop', 'long_text', 'p')]), 'field [kop] twice'],
    'empty choice' => [new ScanType('hero', 'Hero', null, 'f', 1, [new ScanField('icoon', 'choice', 'svg')]), 'has no options'],
    'empty list' => [new ScanType('hero', 'Hero', null, 'f', 1, [new ScanField('items', 'items', 'ul', item: 'li')]), 'has no fields'],
    'list in a list' => [new ScanType('hero', 'Hero', null, 'f', 1, [new ScanField('items', 'items', 'ul', item: 'li', fields: [
        new ScanField('sub', 'items', 'ol', item: 'li', fields: [new ScanField('regel', 'text', '')]),
    ])]), 'list inside a list'],
    'heading level' => [new ScanType('hero', 'Hero', null, 'f', 1, [new ScanField('kop', 'heading', 'h2', tag: 'h7')]), 'unknown heading level [h7]'],
    'range' => [new ScanType('hero', 'Hero', null, 'f', 1, [new ScanField('items', 'items', 'ul', item: 'li', min: 3, max: 2, fields: [new ScanField('regel', 'text', '')])]), 'invalid min or max'],
]);

it('proposes emphasis for a text or heading the scan found it in', function (): void {
    [$heading, $text, $plain] = scannedType([
        new ScanField('kop', 'heading', 'h3', tag: 'h3', emphasis: true),
        new ScanField('label', 'text', 'span', emphasis: true),
        new ScanField('kop_2', 'heading', 'h2'),
    ])->fields();

    expect([$heading->hasEmphasis(), $heading->getTag(), $text->hasEmphasis(), $plain->hasEmphasis()])->toBe([true, 'h3', true, false]);
});
