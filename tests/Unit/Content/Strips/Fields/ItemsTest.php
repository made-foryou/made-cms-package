<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Strips\Fields\Choice;
use Made\CmsPackage\Content\Strips\Fields\Heading;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Link;
use Made\CmsPackage\Content\Strips\Fields\RichText;
use Made\CmsPackage\Content\Strips\Fields\Text;

const ITEM_A = '0a3e5b9c-1d2f-4a6b-8c7d-9e0f1a2b3c4d';
const ITEM_B = '1b4f6c0d-2e3a-4b7c-9d8e-0f1a2b3c4d5e';

it('holds the fields of every item and its limits', function (): void {
    $fields = [Text::make('titel'), RichText::make('tekst')];
    $items = Items::make('kaarten', $fields);
    $limited = $items->min(1)->max(6);

    expect($items->getFields())->toBe($fields)
        ->and($items->getMin())->toBeNull()
        ->and($items->getMax())->toBeNull()
        ->and($limited->getMin())->toBe(1)
        ->and($limited->getMax())->toBe(6)
        ->and($items->getDefault())->toBe([]);
});

it('validates the list, every item, its id and the fields in an item', function (): void {
    $rules = Items::make('kaarten', [Text::make('titel')->required(), RichText::make('tekst')])
        ->min(1)
        ->max(6)
        ->rules('kaarten');

    expect($rules)->toBe([
        'kaarten' => ['nullable', 'array', 'list', 'min:1', 'max:6'],
        'kaarten.*' => ['array:id,titel,tekst'],
        'kaarten.*.id' => ['nullable', 'uuid'],
        'kaarten.*.titel' => ['required', 'string', 'max:255'],
        'kaarten.*.tekst' => ['nullable', 'string', 'max:65535'],
    ]);
});

it('labels the list, an item by its name and the fields in an item', function (): void {
    expect(Items::make('kaarten', [Text::make('titel')])->itemLabel('Kaart')->attributes('kaarten'))->toBe([
        'kaarten' => 'Kaarten',
        'kaarten.*' => 'Kaart',
        'kaarten.*.titel' => 'Titel',
    ]);
});

it('cleans the rich text in every item', function (): void {
    $items = Items::make('kaarten', [Text::make('titel'), RichText::make('tekst')]);

    $normalized = $items->normalize([
        ['id' => ITEM_A, 'titel' => '<b>Een</b>', 'tekst' => '<p>Een</p><script>x</script>'],
        ['id' => ITEM_B, 'titel' => 'Twee'],
    ]);

    expect($normalized)->toBe([
        ['id' => ITEM_A, 'titel' => '<b>Een</b>', 'tekst' => '<p>Een</p>'],
        ['id' => ITEM_B, 'titel' => 'Twee'],
    ]);
});

it('gives every stored item an id of its own and keeps the id it has', function (): void {
    $items = Items::make('kaarten', [Text::make('titel')]);

    /** @var list<array<string, mixed>> $normalized */
    $normalized = $items->normalize([
        ['titel' => 'Een', 'id' => ITEM_A],
        ['titel' => 'Twee'],
        ['id' => ITEM_A, 'titel' => 'Drie'],
        ['id' => '', 'titel' => 'Vier'],
    ]);

    $ids = array_column($normalized, 'id');

    expect($ids[0])->toBe(ITEM_A)
        ->and($ids[1])->toBeUuid()
        ->and($ids[2])->toBeUuid()
        ->and($ids[3])->toBeUuid()
        ->and(array_unique($ids))->toHaveCount(4)
        ->and(array_keys($normalized[0]))->toBe(['id', 'titel'])
        ->and(array_column($normalized, 'titel'))->toBe(['Een', 'Twee', 'Drie', 'Vier']);
});

it('leaves a value that is not a list of items alone', function (): void {
    expect(Items::make('kaarten', [Text::make('titel')])->normalize('geen lijst'))->toBe('geen lijst')
        ->and(Items::make('kaarten', [Text::make('titel')])->normalize(['geen item']))->toBe(['geen item']);
});

it('renders every item with its id and every field, a missing one from its default', function (): void {
    $items = Items::make('knoppen', [
        Text::make('tekst'),
        Link::make('gaat_naar'),
        Choice::make('stijl', ['primary', 'secondary'])->default('primary'),
    ]);

    expect($items->render([
        ['id' => ITEM_A, 'tekst' => 'Lid worden', 'gaat_naar' => ['url' => '/lid-worden'], 'stijl' => 'secondary'],
        ['tekst' => 'Contact'],
    ]))->toBe([
        ['id' => ITEM_A, 'tekst' => 'Lid worden', 'gaat_naar' => '/lid-worden', 'stijl' => 'secondary'],
        ['id' => null, 'tekst' => 'Contact', 'gaat_naar' => null, 'stijl' => 'primary'],
    ]);
});

it('renders the rich text of an item cleaned', function (): void {
    expect(Items::make('kaarten', [RichText::make('tekst')])->render([['tekst' => '<p>Een</p><script>alert(1)</script>']]))
        ->toBe([['id' => null, 'tekst' => '<p>Een</p>']]);
});

it('renders no list as an empty list', function (mixed $value): void {
    expect(Items::make('kaarten', [Text::make('titel')])->render($value))->toBe([]);
})->with([
    'nothing' => [null],
    'no array' => ['geen lijst'],
]);

it('leaves out what is not an item and fields the type no longer has', function (): void {
    expect(Items::make('kaarten', [Text::make('titel')])->render([['titel' => 'Een', 'oud' => 'x'], 'geen item']))
        ->toBe([['id' => null, 'titel' => 'Een']]);
});

it('merges an item with the stored item of the same id and keeps the fields it leaves out', function (): void {
    $items = Items::make('knoppen', [Text::make('tekst'), Choice::make('stijl', ['primary', 'secondary'])]);

    $merged = $items->merge(
        [['id' => ITEM_A, 'tekst' => 'Lid worden', 'stijl' => 'primary'], ['id' => ITEM_B, 'tekst' => 'Contact', 'stijl' => 'secondary']],
        [['id' => ITEM_A, 'tekst' => 'Word lid'], ['id' => ITEM_B]],
    );

    expect($merged)->toBe([
        ['id' => ITEM_A, 'tekst' => 'Word lid', 'stijl' => 'primary'],
        ['id' => ITEM_B, 'tekst' => 'Contact', 'stijl' => 'secondary'],
    ]);
});

it('takes the order of the list it gets and leaves out the items it does not get', function (): void {
    $items = Items::make('knoppen', [Text::make('tekst')]);

    $merged = $items->merge(
        [['id' => ITEM_A, 'tekst' => 'Een'], ['id' => ITEM_B, 'tekst' => 'Twee']],
        [['id' => ITEM_B]],
    );

    expect($merged)->toBe([['id' => ITEM_B, 'tekst' => 'Twee']]);
});

it('treats an item without a known id as a new item', function (): void {
    $items = Items::make('knoppen', [Text::make('tekst')]);

    $merged = $items->merge(
        [['id' => ITEM_A, 'tekst' => 'Een']],
        [['tekst' => 'Nieuw'], ['id' => ITEM_B, 'tekst' => 'Ook nieuw'], ['id' => ITEM_A], ['id' => ITEM_A, 'tekst' => 'Kopie']],
    );

    expect($merged)->toBe([
        ['tekst' => 'Nieuw'],
        ['tekst' => 'Ook nieuw'],
        ['id' => ITEM_A, 'tekst' => 'Een'],
        ['tekst' => 'Kopie'],
    ]);
});

it('drops the stored fields of an item that the type no longer has', function (): void {
    $items = Items::make('knoppen', [Text::make('tekst')]);

    expect($items->merge([['id' => ITEM_A, 'tekst' => 'Een', 'oud' => 'x']], [['id' => ITEM_A]]))
        ->toBe([['id' => ITEM_A, 'tekst' => 'Een']]);
});

it('merges a list of items into nothing stored as new items', function (mixed $stored): void {
    expect(Items::make('knoppen', [Text::make('tekst')])->merge($stored, [['id' => ITEM_A, 'tekst' => 'Een']]))
        ->toBe([['tekst' => 'Een']]);
})->with([
    'nothing' => [null],
    'no list' => ['geen lijst'],
]);

it('leaves a value that is not a list of items alone when it merges', function (): void {
    $items = Items::make('knoppen', [Text::make('tekst')]);

    expect($items->merge([['id' => ITEM_A, 'tekst' => 'Een']], 'geen lijst'))->toBe('geen lijst')
        ->and($items->merge([['id' => ITEM_A, 'tekst' => 'Een']], null))->toBeNull()
        ->and($items->merge([], ['geen item']))->toBe(['geen item']);
});

it('keeps the stored level of a heading in every item by its id, and gives a new item the default level', function (): void {
    $items = Items::make('kaarten', [Heading::make('titel')->tag('h3'), Text::make('tekst')]);

    $kept = $items->keepLevel(
        [['id' => ITEM_A, 'titel' => ['text' => 'Een', 'tag' => 'h4'], 'tekst' => 'x'], ['id' => ITEM_B, 'titel' => ['text' => 'Twee', 'tag' => 'h2']]],
        [
            ['id' => ITEM_B, 'titel' => ['text' => 'Twee!', 'tag' => 'h1']],
            ['titel' => ['text' => 'Nieuw', 'tag' => 'h1'], 'tekst' => 'y'],
            ['id' => ITEM_A, 'titel' => ['text' => 'Een', 'tag' => 'h1'], 'tekst' => 'z'],
            ['id' => '2c5a7d1e-3f4b-4c8d-ae9f-1a2b3c4d5e6f', 'titel' => ['text' => 'Onbekend', 'tag' => 'h1']],
        ],
    );

    expect($kept)->toBe([
        ['id' => ITEM_B, 'titel' => ['text' => 'Twee!', 'tag' => 'h2']],
        ['titel' => ['text' => 'Nieuw', 'tag' => 'h3'], 'tekst' => 'y'],
        ['id' => ITEM_A, 'titel' => ['text' => 'Een', 'tag' => 'h4'], 'tekst' => 'z'],
        ['id' => '2c5a7d1e-3f4b-4c8d-ae9f-1a2b3c4d5e6f', 'titel' => ['text' => 'Onbekend', 'tag' => 'h3']],
    ]);
});

it('gives every heading in a list the default level when nothing is stored', function (mixed $stored): void {
    expect(Items::make('kaarten', [Heading::make('titel')->tag('h3')])->keepLevel($stored, [['id' => ITEM_A, 'titel' => ['text' => 'Een', 'tag' => 'h1']]]))
        ->toBe([['id' => ITEM_A, 'titel' => ['text' => 'Een', 'tag' => 'h3']]]);
})->with([
    'nothing' => [null],
    'no list' => ['geen lijst'],
]);

it('leaves a value that is not a list of items alone when it keeps the levels', function (): void {
    $items = Items::make('kaarten', [Heading::make('titel')]);

    expect($items->keepLevel([], 'geen lijst'))->toBe('geen lijst')
        ->and($items->keepLevel([], null))->toBeNull()
        ->and($items->keepLevel([], ['geen item']))->toBe(['geen item']);
});

it('names one item, "Item" when the site does not', function (): void {
    $items = Items::make('knoppen', [Text::make('tekst')]);
    $named = $items->itemLabel('Knop');

    expect($items->getItemLabel())->toBe('Item')
        ->and($named->getItemLabel())->toBe('Knop')
        ->and($named)->not->toBe($items);
});

it('describes its limits, the name of an item and the fields of every item', function (): void {
    $description = Items::make('knoppen', [Text::make('tekst')->required(), Link::make('gaat_naar')])
        ->itemLabel('Knop')
        ->min(1)
        ->max(2)
        ->describe();

    expect($description['min'])->toBe(1)
        ->and($description['max'])->toBe(2)
        ->and($description['item_label'])->toBe('Knop')
        ->and($description['default'])->toBe([])
        ->and(array_column($description['fields'], 'key'))->toBe(['tekst', 'gaat_naar'])
        ->and($description['fields'][0])->toMatchArray(['type' => 'text', 'required' => true]);
});

it('describes a list without limits', function (): void {
    $description = Items::make('knoppen', [Text::make('tekst')])->describe();

    expect($description['min'])->toBeNull()
        ->and($description['max'])->toBeNull();
});
