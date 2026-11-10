<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\RecognisedField;
use Made\CmsPackage\Content\Data\RecognisedPage;
use Made\CmsPackage\Content\Data\RecognisedStrip;
use Made\CmsPackage\Content\Enums\ScanDoubt;
use Made\CmsPackage\Content\Enums\ScanRole;
use Made\CmsPackage\Content\Strips\Fields\Button;
use Made\CmsPackage\Content\Strips\Fields\Choice;
use Made\CmsPackage\Content\Strips\Fields\Heading;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Link;
use Made\CmsPackage\Content\Strips\Fields\LongText;
use Made\CmsPackage\Content\Strips\Fields\RichText;
use Made\CmsPackage\Content\Strips\Fields\Text;

/**
 * @return array<string, mixed>
 */
function recognisedPageArray(): array
{
    return [
        'url' => '/',
        'title' => 'Home',
        'strips' => [[
            'fingerprint' => 'abc',
            'hint' => '#over-ons',
            'selector' => '#over-ons',
            'doubts' => ['contains_form'],
            'fields' => [
                ['role' => 'heading', 'selector' => 'div > h2', 'value' => ['text' => 'Over ons', 'tag' => 'h2'], 'doubts' => []],
                ['role' => 'image', 'selector' => 'img', 'value' => ['url' => '/foto.jpg', 'alt' => ''], 'doubts' => ['decorative_image']],
                [
                    'role' => 'items',
                    'selector' => 'div:nth-of-type(2)',
                    'value' => null,
                    'doubts' => ['items_differ'],
                    'item' => 'div',
                    'items' => [
                        [['role' => 'icon', 'selector' => 'div > svg', 'value' => ['lucide', 'lucide-users'], 'doubts' => []]],
                        [['role' => 'icon', 'selector' => 'div > svg', 'value' => ['lucide', 'lucide-heart'], 'doubts' => []]],
                    ],
                ],
            ],
        ]],
    ];
}

it('reads and writes what a scan found on one page', function (): void {
    $page = RecognisedPage::fromArray(recognisedPageArray());

    expect($page->strips[0]->fields[0]->role)->toBe(ScanRole::Heading)
        ->and($page->strips[0]->fields[0]->value)->toBe(['text' => 'Over ons', 'tag' => 'h2'])
        ->and($page->strips[0]->doubts)->toBe([ScanDoubt::ContainsForm])
        ->and($page->strips[0]->fields[2]->items[1][0]->value)->toBe(['lucide', 'lucide-heart'])
        ->and($page->toArray())->toBe(recognisedPageArray());
});

it('skips what it cannot read', function (): void {
    $page = RecognisedPage::fromArray([
        'url' => '/',
        'strips' => [
            'geen strook',
            ['fingerprint' => 'abc', 'fields' => [
                ['role' => 'onbekend', 'selector' => 'p'],
                ['role' => 'items', 'items' => ['geen item', [['role' => 'text', 'value' => 'a']]]],
            ]],
        ],
    ]);

    expect($page->title)->toBe('')
        ->and($page->strips)->toHaveCount(1)
        ->and($page->strips[0]->hint)->toBeNull()
        ->and($page->strips[0]->fields)->toHaveCount(1)
        ->and($page->strips[0]->fields[0]->items)->toHaveCount(1)
        ->and($page->strips[0]->fields[0]->items[0][0]->value)->toBe('a');
});

it('gives every role the field type it proposes', function (ScanRole $role, string $type): void {
    expect($role->type())->toBe($type);
})->with([
    [ScanRole::Heading, 'heading'],
    [ScanRole::Text, 'text'],
    [ScanRole::Paragraph, 'long_text'],
    [ScanRole::List, 'long_text'],
    [ScanRole::RichText, 'rich_text'],
    [ScanRole::Button, 'button'],
    [ScanRole::Link, 'link'],
    [ScanRole::Image, 'image'],
    [ScanRole::Icon, 'choice'],
    [ScanRole::Items, 'items'],
]);

it('writes the item tag and the items of a list only', function (): void {
    $text = new RecognisedField(ScanRole::Text, 'span', 'Welkom');
    $items = new RecognisedField(ScanRole::Items, 'ul', null, item: 'li', items: [[$text], [$text]]);

    expect($text->toArray())->toBe(['role' => 'text', 'selector' => 'span', 'value' => 'Welkom', 'doubts' => []])
        ->and($items->toArray())->toHaveKeys(['item', 'items'])
        ->and(new RecognisedStrip('abc', null, 'body > section', [$items]))->fields->toHaveCount(1);
});

it('proposes the types the strip fields of the package have, apart from the image that is still to come', function (): void {
    $types = [
        Text::make('a')->type(), Heading::make('a')->type(), LongText::make('a')->type(), RichText::make('a')->type(), Button::make('a')->type(),
        Link::make('a')->type(), Choice::make('a', ['a'])->type(), Items::make('a', [Text::make('b')])->type(),
    ];

    $proposed = array_unique(array_map(static fn (ScanRole $role): string => $role->type(), ScanRole::cases()));

    expect(array_values(array_diff($proposed, $types)))->toBe(['image']);
});
