<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\RichText;
use Made\CmsPackage\Content\Strips\Fields\Text;

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

it('validates the list, every item and the fields in an item', function (): void {
    $rules = Items::make('kaarten', [Text::make('titel')->required(), RichText::make('tekst')])
        ->min(1)
        ->max(6)
        ->rules('kaarten');

    expect($rules)->toBe([
        'kaarten' => ['nullable', 'array', 'list', 'min:1', 'max:6'],
        'kaarten.*' => ['array:titel,tekst'],
        'kaarten.*.titel' => ['required', 'string', 'max:255'],
        'kaarten.*.tekst' => ['nullable', 'string', 'max:65535'],
    ]);
});

it('labels the list and the fields in an item', function (): void {
    expect(Items::make('kaarten', [Text::make('titel')])->attributes('kaarten'))->toBe([
        'kaarten' => 'Kaarten',
        'kaarten.*.titel' => 'Titel',
    ]);
});

it('cleans the rich text in every item', function (): void {
    $items = Items::make('kaarten', [Text::make('titel'), RichText::make('tekst')]);

    expect($items->normalize([
        ['titel' => '<b>Een</b>', 'tekst' => '<p>Een</p><script>x</script>'],
        ['titel' => 'Twee'],
    ]))->toBe([
        ['titel' => '<b>Een</b>', 'tekst' => '<p>Een</p>'],
        ['titel' => 'Twee'],
    ]);
});

it('leaves a value that is not a list of items alone', function (): void {
    expect(Items::make('kaarten', [Text::make('titel')])->normalize('geen lijst'))->toBe('geen lijst');
});
