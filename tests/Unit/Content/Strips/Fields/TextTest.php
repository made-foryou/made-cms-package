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
