<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\ScanField;
use Made\CmsPackage\Content\Data\ScannedPage;
use Made\CmsPackage\Content\Data\ScanPage;
use Made\CmsPackage\Content\Data\ScanResult;
use Made\CmsPackage\Content\Data\ScanStrip;
use Made\CmsPackage\Content\Data\ScanType;
use Made\CmsPackage\Content\Enums\ScanDoubt;

/**
 * @return array<string, mixed>
 */
function scanResultArray(): array
{
    return [
        'version' => 1,
        'types' => [[
            'key' => 'over-ons',
            'label' => 'Over ons',
            'hint' => '#over-ons',
            'fingerprint' => 'abc',
            'count' => 1,
            'certain' => true,
            'doubts' => [],
            'fields' => [
                ['key' => 'kop', 'type' => 'heading', 'selector' => 'div > h2', 'required' => true, 'certain' => true, 'doubts' => [], 'tag' => 'h2'],
                [
                    'key' => 'items',
                    'type' => 'items',
                    'selector' => 'div:nth-of-type(2)',
                    'required' => true,
                    'certain' => false,
                    'doubts' => ['items_differ'],
                    'item' => 'div',
                    'min' => 1,
                    'max' => 3,
                    'fields' => [
                        ['key' => 'icoon', 'type' => 'choice', 'selector' => 'div > svg', 'required' => true, 'certain' => false, 'doubts' => ['icon_choice'], 'options' => ['users', 'heart']],
                    ],
                ],
            ],
        ]],
        'pages' => [[
            'url' => '/',
            'title' => 'Home',
            'strips' => [
                ['type' => 'over-ons', 'selector' => '#over-ons', 'values' => ['kop' => ['text' => 'Over ons', 'tag' => 'h2'], 'items' => [['icoon' => 'users'], ['icoon' => 'heart']]]],
            ],
        ]],
    ];
}

it('reads and writes the result of a scan', function (): void {
    $result = ScanResult::fromArray(scanResultArray());

    expect($result->types)->toHaveCount(1)
        ->and($result->types[0]->key)->toBe('over-ons')
        ->and($result->types[0]->fields[1]->fields[0]->options)->toBe(['users', 'heart'])
        ->and($result->types[0]->fields[1]->doubts)->toBe([ScanDoubt::ItemsDiffer])
        ->and($result->types[0]->fields[0]->tag)->toBe('h2')
        ->and($result->pages[0]->strips[0]->values['kop'])->toBe(['text' => 'Over ons', 'tag' => 'h2'])
        ->and($result->toArray())->toBe(scanResultArray());
});

it('is certain of a field or type only without doubts', function (): void {
    $field = new ScanField('kop', 'text', 'h2');
    $doubted = new ScanField('foto', 'image', 'img', doubts: [ScanDoubt::DecorativeImage]);
    $type = new ScanType('strook-1', 'Strook 1', null, 'abc', 1, [$field], [ScanDoubt::GenericName]);

    expect($field->certain())->toBeTrue()
        ->and($doubted->certain())->toBeFalse()
        ->and($type->certain())->toBeFalse()
        ->and($type->toArray()['certain'])->toBeFalse();
});

it('writes the options of a choice, the level of a heading and the range and fields of a list only', function (): void {
    $text = new ScanField('kop', 'text', 'h2', options: ['a'], item: 'li', min: 1, max: 2, tag: 'h2');
    $heading = new ScanField('kop', 'heading', 'h3', tag: 'h3');
    $choice = new ScanField('icoon', 'choice', 'svg', options: ['users']);
    $items = new ScanField('items', 'items', 'ul', item: 'li', min: 1, max: 2, fields: [$text]);

    expect($text->toArray())->not->toHaveKeys(['options', 'item', 'min', 'max', 'fields', 'tag'])
        ->and($heading->toArray())->toHaveKey('tag', 'h3')->not->toHaveKeys(['options', 'item', 'min', 'max', 'fields'])
        ->and($choice->toArray())->toHaveKey('options', ['users'])->not->toHaveKeys(['item', 'min', 'max', 'fields'])
        ->and($items->toArray())->toHaveKeys(['item', 'min', 'max', 'fields'])->not->toHaveKey('options');
});

it('reads a broken or partial result without failing', function (): void {
    $result = ScanResult::fromArray([
        'types' => [
            'geen type',
            ['key' => 'hero', 'fields' => ['geen veld', ['key' => 'kop', 'doubts' => ['onbekend', 'nested_list'], 'min' => 'twee', 'tag' => 2]]],
        ],
        'pages' => [['url' => '/', 'strips' => ['geen strook', ['type' => 'hero', 'values' => 'geen waarden']]], 12],
    ]);

    expect($result->types)->toHaveCount(1)
        ->and($result->types[0]->label)->toBe('')
        ->and($result->types[0]->count)->toBe(0)
        ->and($result->types[0]->fields)->toHaveCount(1)
        ->and($result->types[0]->fields[0]->type)->toBe('')
        ->and($result->types[0]->fields[0]->required)->toBeTrue()
        ->and($result->types[0]->fields[0]->doubts)->toBe([ScanDoubt::NestedList])
        ->and($result->types[0]->fields[0]->min)->toBeNull()
        ->and($result->types[0]->fields[0]->tag)->toBeNull()
        ->and($result->pages)->toHaveCount(1)
        ->and($result->pages[0]->title)->toBe('')
        ->and($result->pages[0]->strips)->toHaveCount(1)
        ->and($result->pages[0]->strips[0]->values)->toBe([]);
});

it('finds a type by its key', function (): void {
    $result = ScanResult::fromArray(scanResultArray());

    expect($result->type('over-ons')?->label)->toBe('Over ons')
        ->and($result->type('hero'))->toBeNull();
});

it('holds a scanned page as the browser rendered it', function (): void {
    $page = new ScannedPage('/over-ons', '<html><body><main></main></body></html>');

    expect($page->url)->toBe('/over-ons')
        ->and($page->html)->toContain('<main>');
});

it('builds a page and a strip from their parts', function (): void {
    $page = new ScanPage('/', 'Home', [new ScanStrip('hero', 'body > main > section', ['kop' => 'Welkom'])]);

    expect($page->toArray())->toBe([
        'url' => '/',
        'title' => 'Home',
        'strips' => [['type' => 'hero', 'selector' => 'body > main > section', 'values' => ['kop' => 'Welkom']]],
    ]);
});

it('writes and reads the emphasis of a text or heading only when it has one', function (): void {
    $heading = new ScanField('kop', 'heading', 'h2', tag: 'h2', emphasis: true);
    $text = new ScanField('label', 'text', 'span', emphasis: true);
    $plain = new ScanField('kop', 'heading', 'h2', tag: 'h2');
    $other = new ScanField('tekst', 'long_text', 'p', emphasis: true);

    expect($heading->toArray())->toHaveKey('emphasis', true)
        ->and($text->toArray())->toHaveKey('emphasis', true)
        ->and($plain->toArray())->not->toHaveKey('emphasis')
        ->and($other->toArray())->not->toHaveKey('emphasis')
        ->and(ScanField::fromArray($heading->toArray())->emphasis)->toBeTrue()
        ->and(ScanField::fromArray($plain->toArray())->emphasis)->toBeFalse();
});
