<?php

declare(strict_types=1);

use Illuminate\Validation\Rules\In;
use Made\CmsPackage\Content\Strips\Fields\Choice;
use Made\CmsPackage\Tests\Fixtures\Strips\ButtonStyle;
use Made\CmsPackage\Tests\Fixtures\Strips\Size;

it('takes the options from a list', function (): void {
    expect(Choice::make('stijl', ['Primair', 'Secundair'])->getOptions())
        ->toBe(['Primair' => 'Primair', 'Secundair' => 'Secundair']);
});

it('takes the options from values and labels', function (): void {
    expect(Choice::make('stijl', ['primary' => 'Primair', 'secondary' => 'Secundair'])->getOptions())
        ->toBe(['primary' => 'Primair', 'secondary' => 'Secundair']);
});

it('takes the options from an enum with labels', function (): void {
    expect(Choice::make('stijl', ButtonStyle::class)->getOptions())
        ->toBe(['primary' => 'Primair', 'secondary' => 'Secundair']);
});

it('names the cases of an enum without labels', function (): void {
    expect(Choice::make('maat', Size::class)->getOptions())->toBe(['s' => 'Small', 'l' => 'Large']);
});

it('has no options for a class that is not a backed enum', function (): void {
    expect(Choice::make('stijl', stdClass::class)->getOptions())->toBe([]);
});

it('only accepts one of the options', function (): void {
    $rules = Choice::make('stijl', ButtonStyle::class)->required()->rules('stijl');

    expect($rules['stijl'][0])->toBe('required')
        ->and($rules['stijl'][1])->toBe('string')
        ->and($rules['stijl'][2])->toBeInstanceOf(In::class)
        ->and((string) $rules['stijl'][2])->toBe('in:"primary","secondary"');
});
