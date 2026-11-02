<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Strips\Fields\Button;
use Made\CmsPackage\Content\Strips\Fields\Link;

it('validates a link as a page or an address', function (): void {
    $rules = Link::make('gaat_naar')->rules('gaat_naar');

    expect($rules)->toHaveKeys(['gaat_naar'])
        ->and(array_slice($rules['gaat_naar'], 0, 2))->toBe(['nullable', 'array:page,url'])
        ->and($rules['gaat_naar'][2])->toBeInstanceOf(Closure::class);
});

it('stores a page as its id and an address without surrounding spaces', function (): void {
    $link = Link::make('gaat_naar');

    expect($link->normalize(['page' => '12']))->toBe(['page' => 12])
        ->and($link->normalize(['page' => 12]))->toBe(['page' => 12])
        ->and($link->normalize(['url' => ' /lid-worden ']))->toBe(['url' => '/lid-worden'])
        ->and($link->normalize(null))->toBeNull();
});

it('validates a button as a text and a link', function (): void {
    $rules = Button::make('knop')->required()->rules('knop');

    expect(array_keys($rules))->toBe(['knop', 'knop.text', 'knop.link'])
        ->and($rules['knop'])->toBe(['required', 'array:text,link'])
        ->and($rules['knop.text'])->toBe(['required_with:knop', 'string', 'max:255'])
        ->and(array_slice($rules['knop.link'], 0, 2))->toBe(['required_with:knop', 'array:page,url'])
        ->and($rules['knop.link'][2])->toBeInstanceOf(Closure::class);
});

it('labels the text and the link of a button', function (): void {
    expect(Button::make('knop')->attributes('knop'))->toBe([
        'knop' => 'Knop',
        'knop.text' => 'Tekst',
        'knop.link' => 'Gaat naar',
    ]);
});

it('stores the link of a button like a link', function (): void {
    expect(Button::make('knop')->normalize(['text' => 'Lid worden', 'link' => ['page' => '3']]))
        ->toBe(['text' => 'Lid worden', 'link' => ['page' => 3]]);
});
