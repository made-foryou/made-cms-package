<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Made\CmsPackage\Content\Strips\StripType;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Hero;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Nested\Cards;

/**
 * @param  array<string, mixed>  $values
 * @return array<string, list<string>>
 */
function stripErrors(StripType $type, array $values): array
{
    return Validator::make($values, $type->rules(), [], $type->attributes())->errors()->toArray();
}

/**
 * @return array<string, mixed>
 */
function validHero(): array
{
    return [
        'kop' => 'TTV Merwestad',
        'knoppen' => [['tekst' => 'Lid worden', 'gaat_naar' => ['url' => '/lid-worden'], 'stijl' => 'primary']],
    ];
}

it('falls back to a label from the class name and no description, view or extra data', function (): void {
    $hero = new Hero;

    expect($hero->label())->toBe('Hero')
        ->and($hero->description())->toBeNull()
        ->and($hero->view())->toBeNull()
        ->and($hero->with(['kop' => 'Welkom']))->toBe([]);
});

it('lets the site override its label, description, view and extra data', function (): void {
    $cards = new Cards;

    expect($cards->label())->toBe('Kaarten')
        ->and($cards->description())->toBe('Een rij kaarten met een titel.')
        ->and($cards->view())->toBe('site.cards')
        ->and($cards->with(['kaarten' => [['titel' => 'Een'], ['titel' => 'Twee']]]))->toBe(['aantal' => 2]);
});

it('collects the rules of all its fields by path', function (): void {
    expect(array_keys((new Hero)->rules()))->toBe([
        'label', 'kop', 'intro', 'tekst',
        'actie', 'actie.text', 'actie.link',
        'knoppen', 'knoppen.*', 'knoppen.*.tekst', 'knoppen.*.gaat_naar', 'knoppen.*.stijl',
    ]);
});

it('labels every path for the validation messages', function (): void {
    expect((new Hero)->attributes())->toBe([
        'label' => 'Label',
        'kop' => 'Kop',
        'intro' => 'Intro',
        'tekst' => 'Tekst',
        'actie' => 'Actie',
        'actie.text' => 'Tekst',
        'actie.link' => 'Gaat naar',
        'knoppen' => 'Knoppen',
        'knoppen.*.tekst' => 'Tekst',
        'knoppen.*.gaat_naar' => 'Gaat naar',
        'knoppen.*.stijl' => 'Stijl',
    ]);
});

it('starts a new strip with the defaults of its fields', function (): void {
    expect((new Hero)->defaults())->toBe([
        'label' => null,
        'kop' => 'Welkom',
        'intro' => null,
        'tekst' => null,
        'actie' => null,
        'knoppen' => [],
    ]);
});

it('accepts valid content', function (): void {
    expect(stripErrors(new Hero, validHero()))->toBe([]);
});

it('refuses content that breaks the definition', function (array $change, string $path): void {
    expect(stripErrors(new Hero, [...validHero(), ...$change]))->toHaveKey($path);
})->with([
    'a required field is missing' => [['kop' => null], 'kop'],
    'a text is too long' => [['kop' => str_repeat('a', 256)], 'kop'],
    'too few items' => [['knoppen' => []], 'knoppen'],
    'too many items' => [['knoppen' => array_fill(0, 3, ['tekst' => 'Ga', 'gaat_naar' => ['url' => '/']])], 'knoppen'],
    'items are no list' => [['knoppen' => ['a' => ['tekst' => 'Ga', 'gaat_naar' => ['url' => '/']]]], 'knoppen'],
    'an item has an unknown field' => [['knoppen' => [['tekst' => 'Ga', 'gaat_naar' => ['url' => '/'], 'kleur' => 'rood']]], 'knoppen.0'],
    'a required field in an item is missing' => [['knoppen' => [['gaat_naar' => ['url' => '/']]]], 'knoppen.0.tekst'],
    'a choice outside the options' => [['knoppen' => [['tekst' => 'Ga', 'gaat_naar' => ['url' => '/'], 'stijl' => 'groot']]], 'knoppen.0.stijl'],
    'an invalid address' => [['knoppen' => [['tekst' => 'Ga', 'gaat_naar' => ['url' => 'javascript:alert(1)']]]], 'knoppen.0.gaat_naar'],
    'a link with a page and an address' => [['knoppen' => [['tekst' => 'Ga', 'gaat_naar' => ['page' => 1, 'url' => '/']]]], 'knoppen.0.gaat_naar'],
]);

it('cleans the values after validation, also inside items', function (): void {
    $normalized = (new Hero)->normalize([
        'kop' => '<b>Welkom</b>',
        'tekst' => '<h2>Over</h2><p>Wij <strong>spelen</strong>.</p><script>x</script>',
        'actie' => ['text' => 'Ga', 'link' => ['page' => '4']],
        'knoppen' => [['tekst' => 'Ga', 'gaat_naar' => ['url' => ' /lid-worden ']]],
    ]);

    expect($normalized)->toBe([
        'kop' => '<b>Welkom</b>',
        'tekst' => 'Over<p>Wij <strong>spelen</strong>.</p>',
        'actie' => ['text' => 'Ga', 'link' => ['page' => 4]],
        'knoppen' => [['tekst' => 'Ga', 'gaat_naar' => ['url' => '/lid-worden']]],
    ]);
});
