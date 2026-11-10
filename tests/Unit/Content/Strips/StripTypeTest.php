<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Made\CmsPackage\Content\Strips\StripType;
use Made\CmsPackage\Tests\Fixtures\Strips\Banner;
use Made\CmsPackage\Tests\Fixtures\Strips\Teaser;
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
        'knoppen', 'knoppen.*', 'knoppen.*.id', 'knoppen.*.tekst', 'knoppen.*.gaat_naar', 'knoppen.*.stijl',
        'foto',
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
        'knoppen.*' => 'Item',
        'knoppen.*.tekst' => 'Tekst',
        'knoppen.*.gaat_naar' => 'Gaat naar',
        'knoppen.*.stijl' => 'Stijl',
        'foto' => 'Foto',
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
        'foto' => null,
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
        'knoppen' => [['id' => '6f1d2c3b-4a5e-4f60-8b7c-1d2e3f4a5b6c', 'tekst' => 'Ga', 'gaat_naar' => ['url' => ' /lid-worden ']]],
    ]);

    expect($normalized)->toBe([
        'kop' => '<b>Welkom</b>',
        'tekst' => 'Over<p>Wij <strong>spelen</strong>.</p>',
        'actie' => ['text' => 'Ga', 'link' => ['page' => 4]],
        'knoppen' => [['id' => '6f1d2c3b-4a5e-4f60-8b7c-1d2e3f4a5b6c', 'tekst' => 'Ga', 'gaat_naar' => ['url' => '/lid-worden']]],
    ]);
});

it('merges new values into the stored ones: a field it gets replaces, a field it does not get stays', function (): void {
    $stored = [
        'kop' => 'Oud',
        'intro' => 'Blijft',
        'knoppen' => [['id' => '6f1d2c3b-4a5e-4f60-8b7c-1d2e3f4a5b6c', 'tekst' => 'Lid worden', 'gaat_naar' => ['url' => '/lid-worden'], 'stijl' => 'primary']],
        'oud' => 'x',
    ];

    expect((new Hero)->merge($stored, [
        'kop' => 'Nieuw',
        'tekst' => null,
        'knoppen' => [['id' => '6f1d2c3b-4a5e-4f60-8b7c-1d2e3f4a5b6c', 'tekst' => 'Word lid'], ['tekst' => 'Contact']],
        'onbekend' => 'y',
    ]))->toBe([
        'kop' => 'Nieuw',
        'intro' => 'Blijft',
        'knoppen' => [
            ['id' => '6f1d2c3b-4a5e-4f60-8b7c-1d2e3f4a5b6c', 'tekst' => 'Word lid', 'gaat_naar' => ['url' => '/lid-worden'], 'stijl' => 'primary'],
            ['tekst' => 'Contact'],
        ],
        'oud' => 'x',
        'tekst' => null,
        'onbekend' => 'y',
    ]);
});

it('keeps the stored levels of its headings, also in a list, and leaves the other fields alone', function (): void {
    $card = '6f1d2c3b-4a5e-4f60-8b7c-1d2e3f4a5b6c';
    $stored = ['kop' => ['text' => 'Welkom', 'tag' => 'h3'], 'label' => 'Oud', 'kaarten' => [['id' => $card, 'titel' => ['text' => 'Een', 'tag' => 'h2']]]];

    expect((new Banner)->keepLevels($stored, [
        'kop' => ['text' => 'Hallo', 'tag' => 'h1'],
        'label' => 'Nieuw',
        'kaarten' => [['id' => $card, 'titel' => ['text' => 'Een', 'tag' => 'h3']], ['titel' => ['text' => 'Twee', 'tag' => 'h2']]],
        'onbekend' => 'y',
    ]))->toBe([
        'kop' => ['text' => 'Hallo', 'tag' => 'h3'],
        'label' => 'Nieuw',
        'kaarten' => [['id' => $card, 'titel' => ['text' => 'Een', 'tag' => 'h2']], ['titel' => ['text' => 'Twee', 'tag' => 'h3']]],
        'onbekend' => 'y',
    ]);
});

it('keeps the levels of the fields it gets only', function (): void {
    expect((new Banner)->keepLevels(['kop' => ['text' => 'Welkom', 'tag' => 'h3']], ['label' => 'Nieuw']))->toBe(['label' => 'Nieuw']);
});

it('renders every field in the order of its definition, a missing one from its default', function (): void {
    expect((new Hero)->render(['tekst' => '<p>Hoi</p>', 'kop' => 'TTV Merwestad', 'oud' => 'x']))->toBe([
        'label' => null,
        'kop' => 'TTV Merwestad',
        'intro' => null,
        'tekst' => '<p>Hoi</p>',
        'actie' => null,
        'knoppen' => [],
        'foto' => null,
    ]);

    expect((new Hero)->render([])['kop'])->toBe('Welkom');
});

it('renders the links and buttons inside its fields', function (): void {
    $rendered = (new Hero)->render([
        'actie' => ['text' => 'Ga', 'link' => ['url' => '/ga']],
        'knoppen' => [['tekst' => 'Lid worden', 'gaat_naar' => ['url' => '/lid-worden']]],
    ]);

    expect($rendered['actie'])->toBe(['text' => 'Ga', 'url' => '/ga'])
        ->and($rendered['knoppen'])->toBe([['id' => null, 'tekst' => 'Lid worden', 'gaat_naar' => '/lid-worden', 'stijl' => null]]);
});

it('adds the extra data of the site to the rendered values', function (): void {
    expect((new Cards)->render(['kaarten' => [['titel' => 'Een'], ['titel' => 'Twee']]]))->toBe([
        'kaarten' => [['id' => null, 'titel' => 'Een', 'icoon' => null], ['id' => null, 'titel' => 'Twee', 'icoon' => null]],
        'aantal' => 2,
    ]);
});

it('hands the extra data the rendered values and lets it override a field', function (): void {
    expect((new Teaser)->render(['kop' => 'Van de redacteur', 'lees_meer' => ['url' => '/nieuws']]))->toBe([
        'kop' => 'Uit with',
        'lees_meer' => '/nieuws',
        'doel' => '/nieuws',
        'type' => 'overschreven',
    ]);
});

it('describes itself for the side panel: its key, name, explanation and fields in order', function (): void {
    $description = (new Cards)->describe();

    expect($description['key'])->toBe('cards')
        ->and($description['label'])->toBe('Kaarten')
        ->and($description['description'])->toBe('Een rij kaarten met een titel.')
        ->and(array_column($description['fields'], 'key'))->toBe(['kaarten'])
        ->and(array_column((new Hero)->describe()['fields'], 'type'))
        ->toBe(['text', 'text', 'long_text', 'rich_text', 'button', 'items', 'image']);
});

it('names the fields that are headings for the overview of the headings, a heading in a list with its list', function (): void {
    expect((new Banner)->headings())->toBe([
        ['path' => 'kop', 'list' => null],
        ['path' => 'kaarten.*.titel', 'list' => 'Kaarten'],
    ])->and((new Hero)->headings())->toBe([]);
});

it('validates with messages of its own, in the language of the CMS', function (array $values, string $path, string $message): void {
    app()->setLocale('nl');
    $hero = new Hero;

    $errors = Validator::make([...validHero(), ...$values], $hero->rules(), $hero->messages(), $hero->attributes())
        ->errors()
        ->toArray();

    expect($errors[$path][0] ?? null)->toBe($message);
})->with([
    'required' => [['kop' => null], 'kop', 'Vul Kop in.'],
    'string' => [['label' => ['a']], 'label', 'Label moet tekst zijn.'],
    'too long' => [['label' => str_repeat('a', 256)], 'label', 'Label mag niet langer zijn dan 255 tekens.'],
    'not a list' => [['knoppen' => 'nee'], 'knoppen', 'Knoppen heeft niet de juiste vorm.'],
    'too few' => [['knoppen' => []], 'knoppen', 'Knoppen heeft er minstens 1 nodig.'],
    'too many' => [['knoppen' => array_fill(0, 3, validHero()['knoppen'][0])], 'knoppen', 'Knoppen mag er niet meer dan 2 hebben.'],
    'not one of the options' => [['knoppen' => [[...validHero()['knoppen'][0], 'stijl' => 'groot']]], 'knoppen.0.stijl', 'Kies bij Stijl een van de mogelijkheden.'],
    'part of a button' => [['actie' => ['link' => ['url' => '/']]], 'actie.text', 'Vul Tekst in.'],
]);

it('validates with English messages too', function (): void {
    app()->setLocale('en');
    $hero = new Hero;

    $errors = Validator::make(['kop' => null], $hero->rules(), $hero->messages(), $hero->attributes())->errors();

    expect($errors->first('kop'))->toBe('Fill in Kop.');
});

it('sums up a strip by its first text, then by the items of a list', function (array $values, ?string $summary): void {
    expect((new Hero)->summary($values))->toBe($summary);
})->with([
    'the first text in the order of the fields' => [['label' => '', 'kop' => 'TTV Merwestad', 'intro' => 'Sinds 1962'], 'TTV Merwestad'],
    'a missing field from its default' => [['label' => null], 'Welkom'],
    'a list only without any text' => [['kop' => '', 'knoppen' => [['tekst' => 'Lid worden'], ['tekst' => 'Contact']]], 'Lid worden, Contact'],
    'nothing to show' => [['kop' => '', 'knoppen' => []], null],
]);

it('keeps a summary short', function (): void {
    expect(mb_strlen((string) (new Hero)->summary(['kop' => str_repeat('a', 200)])))->toBeLessThanOrEqual(123);
});
