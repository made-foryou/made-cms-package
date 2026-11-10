<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Strips\Fields\Button;
use Made\CmsPackage\Content\Strips\Fields\Choice;
use Made\CmsPackage\Content\Strips\Fields\Field;
use Made\CmsPackage\Content\Strips\Fields\Heading;
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
    'heading' => [fn (): Field => Heading::make('kop'), 'heading'],
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
    'heading' => [fn (): Field => Heading::make('kop'), 255],
    'long text' => [fn (): Field => LongText::make('intro'), 65535],
    'rich text' => [fn (): Field => RichText::make('tekst'), 65535],
]);

it('labels the parts of a button', function (): void {
    expect(Button::make('actie')->describe()['parts'])->toBe(['text' => 'Tekst', 'link' => 'Gaat naar']);
});

it('sums up its value as a short text for the lists of the CMS', function (Field $field, mixed $value, ?string $summary): void {
    expect($field->summary($value))->toBe($summary);
})->with([
    'text' => [Text::make('kop'), '  Welkom bij de club ', 'Welkom bij de club'],
    'empty text' => [Text::make('kop'), '   ', null],
    'no text' => [Text::make('kop'), null, null],
    'long text, first line' => [LongText::make('intro'), "\nSamen spelen\nop dinsdag", 'Samen spelen'],
    'rich text without markup' => [RichText::make('tekst'), '<p>Meer dan <strong>alleen</strong> tafeltennis</p><p>Echt.</p>', 'Meer dan alleen tafeltennis Echt.'],
    'button by its text' => [Button::make('actie'), ['text' => 'Lid worden', 'link' => ['url' => '/lid']], 'Lid worden'],
    'button without text' => [Button::make('actie'), ['text' => '', 'link' => ['url' => '/lid']], null],
    'heading by its text' => [Heading::make('kop'), ['text' => 'Welkom bij de club', 'tag' => 'h1'], 'Welkom bij de club'],
    'link' => [Link::make('gaat_naar'), ['url' => '/lid'], null],
    'choice' => [Choice::make('stijl', ['groot' => 'Groot']), 'groot', null],
    'items by their titles' => [
        Items::make('teams', [Choice::make('klasse', ['a' => 'A']), Heading::make('naam'), Text::make('captain')]),
        [['klasse' => 'a', 'naam' => ['text' => 'Merwestad 1', 'tag' => 'h3'], 'captain' => 'Piet'], ['naam' => '', 'captain' => 'Klaas'], ['naam' => null]],
        'Merwestad 1, Klaas',
    ],
    'items without text' => [Items::make('teams', [Text::make('naam')]), [['naam' => ''], []], null],
    'no items' => [Items::make('teams', [Text::make('naam')]), null, null],
]);
