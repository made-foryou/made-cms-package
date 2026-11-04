<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Strips\Fields\Button;
use Made\CmsPackage\Content\Strips\Fields\Choice;
use Made\CmsPackage\Content\Strips\Fields\Field;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Link;
use Made\CmsPackage\Content\Strips\Fields\LongText;
use Made\CmsPackage\Content\Strips\Fields\RichText;
use Made\CmsPackage\Content\Strips\Fields\Text;

it('names its type for the side panel', function (Field $field, string $type): void {
    expect($field->type())->toBe($type)
        ->and($field->describe()['type'])->toBe($type);
})->with([
    'text' => [fn (): Field => Text::make('kop'), 'text'],
    'long text' => [fn (): Field => LongText::make('intro'), 'long_text'],
    'rich text' => [fn (): Field => RichText::make('tekst'), 'rich_text'],
    'link' => [fn (): Field => Link::make('gaat_naar'), 'link'],
    'button' => [fn (): Field => Button::make('actie'), 'button'],
    'choice' => [fn (): Field => Choice::make('stijl', ['Primair']), 'choice'],
    'items' => [fn (): Field => Items::make('knoppen', [Text::make('tekst')]), 'items'],
]);

it('describes its key, label, hint, whether it is required and its default', function (): void {
    expect(Text::make('kop')->label('Titel')->hint('Kort en krachtig.')->required()->default('Welkom')->describe())
        ->toBe([
            'key' => 'kop',
            'type' => 'text',
            'label' => 'Titel',
            'hint' => 'Kort en krachtig.',
            'required' => true,
            'default' => 'Welkom',
            'max_length' => 255,
        ]);
});

it('describes a field without a label, hint or default', function (): void {
    expect(Link::make('gaat_naar')->describe())->toBe([
        'key' => 'gaat_naar',
        'type' => 'link',
        'label' => 'Gaat naar',
        'hint' => null,
        'required' => false,
        'default' => null,
    ]);
});

it('tells the panel how long a text may be', function (Field $field, int $length): void {
    expect($field->describe()['max_length'])->toBe($length);
})->with([
    'text' => [fn (): Field => Text::make('kop'), 255],
    'long text' => [fn (): Field => LongText::make('intro'), 65535],
    'rich text' => [fn (): Field => RichText::make('tekst'), 65535],
]);

it('labels the parts of a button', function (): void {
    expect(Button::make('actie')->describe()['parts'])->toBe(['text' => 'Tekst', 'link' => 'Gaat naar']);
});
