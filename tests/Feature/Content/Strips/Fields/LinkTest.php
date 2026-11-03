<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\Fields\Button;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Link;

/**
 * @param  array<string, mixed>  $values
 * @return array<string, list<string>>
 */
function linkErrors(array $values): array
{
    $field = Items::make('knoppen', [Button::make('knop')->required(), Link::make('extra')]);

    return Validator::make($values, $field->rules('knoppen'), [], $field->attributes('knoppen'))->errors()->toArray();
}

it('accepts an existing page', function (): void {
    $page = Page::factory()->create();

    expect(linkErrors(['knoppen' => [['knop' => ['text' => 'Lid worden', 'link' => ['page' => $page->id]]]]]))->toBe([]);
});

it('refuses a page that does not exist', function (): void {
    expect(linkErrors(['knoppen' => [['knop' => ['text' => 'Lid worden', 'link' => ['page' => 999]]]]]))
        ->toBe(['knoppen.0.knop.link' => ['De gekozen pagina bij Gaat naar bestaat niet.']]);
});

it('accepts the addresses an editor may type', function (string $url): void {
    expect(linkErrors(['knoppen' => [['knop' => ['text' => 'Ga', 'link' => ['url' => $url]]]]]))->toBe([]);
})->with([
    'https://example.com/pad?a=1',
    'http://example.com',
    'mailto:info@example.com',
    'tel:+31612345678',
    '/',
    '/lid-worden',
    '#over-ons',
]);

it('refuses addresses that are no link', function (string $url): void {
    expect(linkErrors(['knoppen' => [['knop' => ['text' => 'Ga', 'link' => ['url' => $url]]]]]))
        ->toBe(['knoppen.0.knop.link' => ['Het adres bij Gaat naar is ongeldig. Begin met https://, mailto:, tel:, / of #.']]);
})->with([
    'javascript:alert(1)',
    '//example.com',
    'example.com',
    'https://example.com/met spatie',
    'https://example.com/'.str_repeat('a', 2048),
]);

it('wants either a page or an address', function (array $link): void {
    expect(linkErrors(['knoppen' => [['knop' => ['text' => 'Ga', 'link' => $link]]]]))
        ->toBe(['knoppen.0.knop.link' => ['Kies bij Gaat naar een pagina of vul een adres in.']]);
})->with([
    'both' => [['page' => 1, 'url' => '/x']],
    'neither' => [['page' => null, 'url' => null]],
]);

it('refuses other keys in a link', function (): void {
    expect(linkErrors(['knoppen' => [['knop' => ['text' => 'Ga', 'link' => ['href' => '/x']]]]]))
        ->toHaveKey('knoppen.0.knop.link');
});

it('leaves an optional link empty', function (): void {
    expect(linkErrors(['knoppen' => [['knop' => ['text' => 'Ga', 'link' => ['url' => '/']], 'extra' => null]]]))->toBe([]);
});

it('needs the text and the link of a button', function (): void {
    expect(array_keys(linkErrors(['knoppen' => [['knop' => ['text' => '']]]])))->toBe(['knoppen.0.knop.text', 'knoppen.0.knop.link']);
});

it('renders a page as the current path of its address', function (): void {
    $page = Page::factory()->published()->at('/over-ons/team')->create();

    expect(Link::make('gaat_naar')->render(['page' => $page->id]))->toBe('/over-ons/team');
});

it('renders a page a visitor cannot open as null', function (Closure $page): void {
    expect(Link::make('gaat_naar')->render(['page' => $page()]))->toBeNull();
})->with([
    'a draft page' => [fn (): int => Page::factory()->at('/concept')->create()->id],
    'a page without an address' => [fn (): int => Page::factory()->published()->create()->id],
    'a page that is gone' => [fn (): int => 999],
]);

it('renders a button to a page with the path of the page', function (): void {
    $page = Page::factory()->published()->at('/lid-worden')->create();

    expect(Button::make('knop')->render(['text' => 'Lid worden', 'link' => ['page' => $page->id]]))
        ->toBe(['text' => 'Lid worden', 'url' => '/lid-worden']);
});

it('renders a button to a page that is gone as null', function (): void {
    expect(Button::make('knop')->render(['text' => 'Lid worden', 'link' => ['page' => 999]]))->toBeNull();
});
