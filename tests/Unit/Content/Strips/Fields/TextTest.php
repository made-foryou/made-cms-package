<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Strips\Fields\LongText;
use Made\CmsPackage\Content\Strips\Fields\Text;

it('derives a readable label from the key', function (): void {
    expect(Text::make('gaat_naar')->getLabel())->toBe('Gaat naar')
        ->and(Text::make('kop')->label('Titel')->getLabel())->toBe('Titel');
});

it('returns a changed copy and leaves the original untouched', function (): void {
    $field = Text::make('kop');
    $changed = $field->label('Titel')->hint('Kort.')->required()->default('Welkom');

    expect($field->getLabel())->toBe('Kop')
        ->and($field->getHint())->toBeNull()
        ->and($field->isRequired())->toBeFalse()
        ->and($field->getDefault())->toBeNull()
        ->and($changed->getKey())->toBe('kop')
        ->and($changed->getLabel())->toBe('Titel')
        ->and($changed->getHint())->toBe('Kort.')
        ->and($changed->isRequired())->toBeTrue()
        ->and($changed->getDefault())->toBe('Welkom');
});

it('validates a single line of text', function (): void {
    expect(Text::make('kop')->rules('kop'))->toBe(['kop' => ['nullable', 'string', 'max:255']])
        ->and(Text::make('kop')->required()->rules('kop'))->toBe(['kop' => ['required', 'string', 'max:255']]);
});

it('validates a longer text', function (): void {
    expect(LongText::make('intro')->rules('intro'))->toBe(['intro' => ['nullable', 'string', 'max:65535']]);
});

it('labels its own path', function (): void {
    expect(Text::make('kop')->attributes('kop'))->toBe(['kop' => 'Kop']);
});

it('keeps the value as it is', function (): void {
    expect(Text::make('kop')->normalize('  Welkom '))->toBe('  Welkom ');
});

it('has no emphasis by default and takes it as a changed copy', function (): void {
    $field = Text::make('kop');
    $emphasis = $field->emphasis();

    expect($field->hasEmphasis())->toBeFalse()
        ->and($emphasis->hasEmphasis())->toBeTrue()
        ->and($emphasis->emphasis(false)->hasEmphasis())->toBeFalse()
        ->and($field->describe()['emphasis'])->toBeFalse()
        ->and($emphasis->describe()['emphasis'])->toBeTrue();
});

it('keeps the asterisks of its emphasis in its stored and rendered value, and counts them in its length', function (): void {
    $field = Text::make('kop')->emphasis();

    expect($field->normalize('Jullie dag in *vertrouwde* handen'))->toBe('Jullie dag in *vertrouwde* handen')
        ->and($field->render('Jullie dag in *vertrouwde* handen'))->toBe('Jullie dag in *vertrouwde* handen')
        ->and($field->rules('kop'))->toBe(['kop' => ['nullable', 'string', 'max:255']]);
});

it('sums up its text without the asterisks of its emphasis, and with them without the option', function (): void {
    expect(Text::make('kop')->emphasis()->summary(' Jullie dag in *vertrouwde* handen '))->toBe('Jullie dag in vertrouwde handen')
        ->and(Text::make('kop')->emphasis()->summary('**'))->toBe('**')
        ->and(Text::make('kop')->emphasis()->summary(null))->toBeNull()
        ->and(Text::make('kop')->summary('Jullie dag in *vertrouwde* handen'))->toBe('Jullie dag in *vertrouwde* handen');
});
