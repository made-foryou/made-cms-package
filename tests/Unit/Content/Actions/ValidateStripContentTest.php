<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\ValidateStripContent;
use Made\CmsPackage\Content\Data\Field;
use Made\CmsPackage\Content\Data\StripType;
use Made\CmsPackage\Content\Enums\FieldType;
use Made\CmsPackage\Content\Exceptions\InvalidStripContent;

beforeEach(function (): void {
    $this->hero = new StripType(key: 'hero', label: 'Hero', fields: [
        new Field(name: 'title', type: FieldType::Text, label: 'Titel'),
        new Field(name: 'intro', type: FieldType::Textarea, label: 'Introductie'),
        new Field(name: 'photo', type: FieldType::Image, label: 'Foto'),
        new Field(name: 'buttons', type: FieldType::List, label: 'Knoppen', fields: [
            new Field(name: 'link', type: FieldType::Link, label: 'Knop'),
        ]),
    ]);
});

it('keeps the content, in the order of the fields', function (): void {
    expect(ValidateStripContent::run($this->hero, [
        'buttons' => [['link' => ['label' => 'Lid worden', 'url' => '#lid-worden']]],
        'photo' => ['src' => '/photos/hal.jpg', 'alt' => 'De sporthal'],
        'intro' => "Sinds 1982.\nVoor iedereen.",
        'title' => 'TTV Merwestad',
    ]))->toBe([
        'title' => 'TTV Merwestad',
        'intro' => "Sinds 1982.\nVoor iedereen.",
        'photo' => ['src' => '/photos/hal.jpg', 'alt' => 'De sporthal'],
        'buttons' => [['link' => ['label' => 'Lid worden', 'url' => '#lid-worden']]],
    ]);
});

it('fills a field without a value with an empty value', function (): void {
    expect(ValidateStripContent::run($this->hero, []))->toBe([
        'title' => '',
        'intro' => '',
        'photo' => null,
        'buttons' => [],
    ]);
});

it('fills a missing alt text or link url with empty text', function (): void {
    expect(ValidateStripContent::run($this->hero, [
        'photo' => ['src' => '/photos/hal.jpg'],
        'buttons' => [['link' => ['label' => 'Lid worden']]],
    ]))->toMatchArray([
        'photo' => ['src' => '/photos/hal.jpg', 'alt' => ''],
        'buttons' => [['link' => ['label' => 'Lid worden', 'url' => '']]],
    ]);
});

it('refuses content that does not fit the strip type', function (mixed $content, string $message): void {
    expect(fn () => ValidateStripContent::run($this->hero, $content))->toThrow(InvalidStripContent::class, $message);
})->with([
    'a list instead of an object' => [['TTV Merwestad'], 'Content of strip type [hero] is invalid: expected an object with the fields of the strip'],
    'no content at all' => [null, 'Content of strip type [hero] is invalid: expected an object'],
    'an unknown field' => [['subtitle' => 'Dordrecht'], 'is invalid at [subtitle]: this field is not in the definition'],
    'a number as text' => [['title' => 1982], 'is invalid at [title]: expected text'],
    'an image as text' => [['photo' => '/photos/hal.jpg'], 'is invalid at [photo]: expected {src, alt}'],
    'an image with an extra key' => [['photo' => ['src' => '/photos/hal.jpg', 'width' => 1200]], 'is invalid at [photo]: expected {src, alt}'],
    'a list as an object' => [['buttons' => ['link' => ['label' => 'Lid worden', 'url' => '#']]], 'is invalid at [buttons]: expected a list of items'],
    'a broken link in a list' => [['buttons' => [['link' => 'Lid worden']]], 'is invalid at [buttons.0.link]: expected {label, url}'],
]);

it('treats an empty image or link like no image or link', function (): void {
    expect(ValidateStripContent::run($this->hero, ['photo' => [], 'buttons' => [['link' => []]]]))
        ->toMatchArray(['photo' => null, 'buttons' => [['link' => null]]]);
});
