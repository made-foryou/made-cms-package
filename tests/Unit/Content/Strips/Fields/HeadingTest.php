<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Made\CmsPackage\Content\Strips\Fields\Heading;
use Made\CmsPackage\Tests\Fixtures\Strips\InlineStripType;

it('is a second level heading with every level to choose from by default', function (): void {
    $heading = Heading::make('kop');

    expect($heading->type())->toBe('heading')
        ->and($heading->getTag())->toBe('h2')
        ->and($heading->getTags())->toBe(['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span']);
});

it('takes its level and the levels to choose from, as a changed copy', function (): void {
    $heading = Heading::make('titel');
    $changed = $heading->tag('h3')->tags(['span', 'h3', 'h2']);

    expect($heading->getTag())->toBe('h2')
        ->and($heading->getTags())->toHaveCount(7)
        ->and($changed->getTag())->toBe('h3')
        ->and($changed->getTags())->toBe(['h2', 'h3', 'span']);
});

it('validates a text and a level out of its levels', function (): void {
    $rules = Heading::make('kop')->required()->tags(['h1', 'h2', 'h3'])->rules('kop');

    expect(array_keys($rules))->toBe(['kop', 'kop.text', 'kop.tag'])
        ->and($rules['kop'])->toBe(['required', 'array:text,tag'])
        ->and($rules['kop.text'])->toBe(['required_with:kop', 'string', 'max:255'])
        ->and(array_slice($rules['kop.tag'], 0, 2))->toBe(['nullable', 'string'])
        ->and((string) $rules['kop.tag'][2])->toBe('in:"h1","h2","h3"');
});

it('lets an optional heading stay empty', function (): void {
    expect(Heading::make('kop')->rules('kop')['kop.text'])->toBe(['nullable', 'string', 'max:255']);
});

it('accepts a text with one of its levels and refuses anything else', function (mixed $value, ?string $error): void {
    $type = new InlineStripType('hero', [Heading::make('kop')->required()->tags(['h1', 'h2', 'h3'])]);
    $validator = Validator::make(['kop' => $value], $type->rules(), $type->messages(), $type->attributes());

    expect($validator->errors()->keys())->toBe($error === null ? [] : [$error]);
})->with([
    'a text with a level' => [['text' => 'Welkom', 'tag' => 'h3'], null],
    'a text without a level' => [['text' => 'Welkom'], null],
    'a level outside its levels' => [['text' => 'Welkom', 'tag' => 'h4'], 'kop.tag'],
    'a level that is no tag' => [['text' => 'Welkom', 'tag' => 'p'], 'kop.tag'],
    'no text' => [['text' => null, 'tag' => 'h2'], 'kop.text'],
    'a text that is too long' => [['text' => str_repeat('a', 256), 'tag' => 'h2'], 'kop.text'],
    'another key' => [['text' => 'Welkom', 'tag' => 'h2', 'class' => 'groot'], 'kop'],
    'nothing' => [null, 'kop'],
]);

it('names the field in the messages about its text and its level', function (): void {
    expect(Heading::make('kop')->attributes('kop'))->toBe([
        'kop' => 'Kop',
        'kop.text' => 'Kop',
        'kop.tag' => 'Niveau',
    ]);
});

it('stores a text with its level, a text that was stored before as its default level and an empty one as nothing', function (mixed $value, mixed $stored): void {
    expect(Heading::make('kop')->tags(['h1', 'h2', 'h3'])->normalize($value))->toBe($stored);
})->with([
    'a text with a level' => [['text' => 'Welkom', 'tag' => 'h3'], ['text' => 'Welkom', 'tag' => 'h3']],
    'the level first' => [['tag' => 'h1', 'text' => 'Welkom'], ['text' => 'Welkom', 'tag' => 'h1']],
    'a text of a former text field' => ['Welkom', ['text' => 'Welkom', 'tag' => 'h2']],
    'a missing level' => [['text' => 'Welkom'], ['text' => 'Welkom', 'tag' => 'h2']],
    'an unknown level' => [['text' => 'Welkom', 'tag' => 'h7'], ['text' => 'Welkom', 'tag' => 'h2']],
    'a level outside its levels' => [['text' => 'Welkom', 'tag' => 'h5'], ['text' => 'Welkom', 'tag' => 'h2']],
    'a level without a text' => [['tag' => 'h3'], ['text' => null, 'tag' => 'h3']],
    'an empty text' => [['text' => '', 'tag' => 'h3'], ['text' => null, 'tag' => 'h3']],
    'the default level without a text' => [['text' => null, 'tag' => 'h2'], null],
    'an empty former text' => ['', null],
    'another key' => [['text' => 'Welkom', 'tag' => 'h2', 'class' => 'groot'], ['text' => 'Welkom', 'tag' => 'h2']],
    'nothing' => [null, null],
]);

it('keeps the level it stored for an editor who does not choose levels', function (mixed $stored, mixed $value, mixed $kept): void {
    expect(Heading::make('kop')->tags(['h1', 'h2', 'h3'])->keepLevel($stored, $value))->toBe($kept);
})->with([
    'another level' => [['text' => 'Welkom', 'tag' => 'h3'], ['text' => 'Hallo', 'tag' => 'h1'], ['text' => 'Hallo', 'tag' => 'h3']],
    'no level' => [['text' => 'Welkom', 'tag' => 'h3'], ['text' => 'Hallo'], ['text' => 'Hallo', 'tag' => 'h3']],
    'a level that is no tag' => [['text' => 'Welkom', 'tag' => 'h3'], ['text' => 'Hallo', 'tag' => 'p'], ['text' => 'Hallo', 'tag' => 'h3']],
    'a level chosen before the text' => [['text' => null, 'tag' => 'h1'], ['text' => 'Hallo', 'tag' => 'h3'], ['text' => 'Hallo', 'tag' => 'h1']],
    'nothing stored' => [null, ['text' => 'Hallo', 'tag' => 'h1'], ['text' => 'Hallo', 'tag' => 'h2']],
    'a former text stored' => ['Welkom', ['text' => 'Hallo', 'tag' => 'h1'], ['text' => 'Hallo', 'tag' => 'h2']],
    'a stored level outside its levels' => [['text' => 'Welkom', 'tag' => 'h5'], ['text' => 'Hallo', 'tag' => 'h1'], ['text' => 'Hallo', 'tag' => 'h2']],
    'emptied at another level' => [['text' => 'Welkom', 'tag' => 'h3'], null, ['text' => null, 'tag' => 'h3']],
    'emptied at the default level' => [['text' => 'Welkom', 'tag' => 'h2'], null, null],
    'another key' => [['text' => 'Welkom', 'tag' => 'h3'], ['text' => 'Hallo', 'tag' => 'h1', 'class' => 'groot'], ['text' => 'Hallo', 'tag' => 'h3', 'class' => 'groot']],
    'something else' => [['text' => 'Welkom', 'tag' => 'h3'], 'Hallo', 'Hallo'],
]);

it('renders a text with its level, or nothing without a text', function (mixed $value, ?array $rendered): void {
    expect(Heading::make('kop')->tag('h1')->tags(['h1', 'h2'])->render($value))->toBe($rendered);
})->with([
    'a text with a level' => [['text' => 'Welkom', 'tag' => 'h2'], ['text' => 'Welkom', 'tag' => 'h2']],
    'a text of a former text field' => ['Welkom', ['text' => 'Welkom', 'tag' => 'h1']],
    'an unknown level' => [['text' => 'Welkom', 'tag' => 'script'], ['text' => 'Welkom', 'tag' => 'h1']],
    'a level the developer took out of its levels' => [['text' => 'Welkom', 'tag' => 'h3'], ['text' => 'Welkom', 'tag' => 'h1']],
    'an empty text' => [['text' => '', 'tag' => 'h2'], null],
    'an empty former text' => ['', null],
    'no text' => [['tag' => 'h2'], null],
    'nothing' => [null, null],
    'something else' => [12, null],
]);

it('gives its default as a text with its level, whatever the order of the definition', function (): void {
    expect(Heading::make('kop')->default('Welkom')->tag('h1')->getDefault())->toBe(['text' => 'Welkom', 'tag' => 'h1'])
        ->and(Heading::make('kop')->tag('h3')->default(['text' => 'Welkom'])->getDefault())->toBe(['text' => 'Welkom', 'tag' => 'h3'])
        ->and(Heading::make('kop')->default(['tag' => 'h1'])->getDefault())->toBe(['text' => null, 'tag' => 'h1'])
        ->and(Heading::make('kop')->getDefault())->toBeNull();
});

it('sums up its text', function (mixed $value, ?string $summary): void {
    expect(Heading::make('kop')->summary($value))->toBe($summary);
})->with([
    'a text with a level' => [['text' => '  Welkom bij de club ', 'tag' => 'h2'], 'Welkom bij de club'],
    'a text of a former text field' => ['Welkom', 'Welkom'],
    'an empty text' => [['text' => ' ', 'tag' => 'h2'], null],
    'nothing' => [null, null],
]);

it('describes its levels with their labels, its default level and the label of its text', function (): void {
    $description = Heading::make('kop')->tag('h3')->tags(['span', 'h3', 'h2'])->default('Welkom')->describe();

    expect($description['type'])->toBe('heading')
        ->and($description['default'])->toBe(['text' => 'Welkom', 'tag' => 'h3'])
        ->and($description['max_length'])->toBe(255)
        ->and($description['tag'])->toBe('h3')
        ->and($description['tags'])->toBe([
            ['value' => 'h2', 'label' => 'H2'],
            ['value' => 'h3', 'label' => 'H3'],
            ['value' => 'span', 'label' => 'Geen kop'],
        ])
        ->and($description['parts'])->toBe(['text' => 'Tekst']);
});

it('labels every level in English too', function (): void {
    app()->setLocale('en');

    expect(array_column(Heading::make('kop')->describe()['tags'], 'label'))
        ->toBe(['H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'No heading']);
});
