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

it('renders an address as its url', function (): void {
    expect(Link::make('gaat_naar')->render(['url' => '/lid-worden']))->toBe('/lid-worden')
        ->and(Link::make('gaat_naar')->render(['url' => 'mailto:info@example.com']))->toBe('mailto:info@example.com');
});

it('renders no link as null', function (mixed $value): void {
    expect(Link::make('gaat_naar')->render($value))->toBeNull();
})->with([
    'nothing' => [null],
    'no array' => ['/lid-worden'],
    'no target' => [['page' => null]],
    'an empty address' => [['url' => '']],
]);

it('renders an address the validation would refuse as null, also when it never passed the validation', function (string $url): void {
    expect(Link::make('gaat_naar')->render(['url' => $url]))->toBeNull()
        ->and(Button::make('knop')->render(['text' => 'Klik', 'link' => ['url' => $url]]))->toBeNull();
})->with([
    'a script' => ['javascript:alert(1)'],
    'a script in capitals' => ['JavaScript:alert(1)'],
    'a script behind a tab' => ["java\tscript:alert(1)"],
    'a script behind spaces' => ['  javascript:alert(1)'],
    'data' => ['data:text/html,<script>alert(1)</script>'],
    'another site without a scheme' => ['//example.com'],
    'another site behind a backslash' => ['/\\example.com'],
    'no scheme' => ['example.com'],
    'too long' => ['https://example.com/'.str_repeat('a', 2048)],
]);

it('renders a button as its text and url', function (): void {
    expect(Button::make('knop')->render(['text' => 'Lid worden', 'link' => ['url' => '/lid-worden']]))
        ->toBe(['text' => 'Lid worden', 'url' => '/lid-worden']);
});

it('renders a button without a text or a link as null', function (mixed $value): void {
    expect(Button::make('knop')->render($value))->toBeNull();
})->with([
    'nothing' => [null],
    'no array' => ['Lid worden'],
    'no text' => [['link' => ['url' => '/lid-worden']]],
    'an empty text' => [['text' => '', 'link' => ['url' => '/lid-worden']]],
    'no link' => [['text' => 'Lid worden']],
    'an empty link' => [['text' => 'Lid worden', 'link' => null]],
]);
