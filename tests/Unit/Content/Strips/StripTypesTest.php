<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Exceptions\DuplicateStripType;
use Made\CmsPackage\Content\Exceptions\InvalidStripType;
use Made\CmsPackage\Content\Exceptions\UnknownStripType;
use Made\CmsPackage\Content\Strips\Fields\Choice;
use Made\CmsPackage\Content\Strips\Fields\Field;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Content\Strips\StripType;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Tests\Fixtures\Strips\InlineStripType;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Hero;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Nested\Cards;

const STRIP_FIXTURES = __DIR__.'/../../../Fixtures/Strips';

it('finds the strip types in the folder and its sub-folders', function (): void {
    $catalogue = StripTypes::discover(STRIP_FIXTURES.'/Valid', 'Made\CmsPackage\Tests\Fixtures\Strips\Valid');

    expect(array_map(static fn (StripType $type): string => $type::class, $catalogue->all()))
        ->toBe([Hero::class, Cards::class]);
});

it('accepts a namespace with surrounding backslashes and a path with a trailing slash', function (): void {
    $catalogue = StripTypes::discover(STRIP_FIXTURES.'/Valid/', '\Made\CmsPackage\Tests\Fixtures\Strips\Valid\\');

    expect($catalogue->has('hero'))->toBeTrue()
        ->and($catalogue->has('cards'))->toBeTrue();
});

it('is empty when the folder does not exist', function (): void {
    expect(StripTypes::discover(STRIP_FIXTURES.'/Missing', 'App\Cms\Strips')->all())->toBe([]);
});

it('skips files whose class does not match the namespace', function (): void {
    expect(StripTypes::discover(STRIP_FIXTURES.'/Valid', 'App\Cms\Strips')->all())->toBe([]);
});

it('finds a strip type by its key', function (): void {
    $hero = new Hero;
    $catalogue = new StripTypes([$hero, new Cards]);

    expect($catalogue->find('hero'))->toBe($hero)
        ->and($catalogue->has('hero'))->toBeTrue()
        ->and($catalogue->has('quote'))->toBeFalse();
});

it('lists the strip types by label', function (): void {
    $catalogue = new StripTypes([new InlineStripType('quote', [Text::make('quote')]), new Hero, new Cards]);

    expect(array_map(static fn (StripType $type): string => $type->label(), $catalogue->all()))
        ->toBe(['Hero', 'Inline Strip Type', 'Kaarten']);
});

it('refuses an unknown key', function (): void {
    expect(fn () => (new StripTypes([new Hero]))->find('quote'))
        ->toThrow(UnknownStripType::class, 'Strip type [quote] is not in the catalogue.');
});

it('refuses two strip types with the same key', function (): void {
    expect(fn () => StripTypes::discover(STRIP_FIXTURES.'/Duplicate', 'Made\CmsPackage\Tests\Fixtures\Strips\Duplicate'))
        ->toThrow(DuplicateStripType::class, 'Strip types [Made\CmsPackage\Tests\Fixtures\Strips\Duplicate\First] and [Made\CmsPackage\Tests\Fixtures\Strips\Duplicate\Second] share the key [hero].');
});

it('refuses a definition that breaks the rules', function (string $key, Closure $fields, string $message): void {
    /** @var list<Field> $list */
    $list = $fields();

    expect(fn () => new StripTypes([new InlineStripType($key, $list)]))
        ->toThrow(InvalidStripType::class, $message);
})->with([
    'a key with capitals' => ['Hero', fn (): array => [Text::make('kop')], 'has an invalid key [Hero]'],
    'a key with a dot' => ['site.hero', fn (): array => [Text::make('kop')], 'has an invalid key [site.hero]'],
    'a field key with a dash' => ['hero', fn (): array => [Text::make('gaat-naar')], 'has an invalid field key [gaat-naar]'],
    'a reserved field' => ['hero', fn (): array => [Text::make('type')], 'may not use the reserved field [type]'],
    'a reserved field in an item' => ['hero', fn (): array => [Items::make('kaarten', [Text::make('id')])], 'may not use the reserved field [id]'],
    'a field twice' => ['hero', fn (): array => [Text::make('kop'), Text::make('kop')], 'has the field [kop] twice'],
    'a field twice in an item' => ['hero', fn (): array => [Items::make('kaarten', [Text::make('titel'), Text::make('titel')])], 'has the field [titel] twice'],
    'a negative minimum' => ['hero', fn (): array => [Items::make('kaarten', [Text::make('titel')])->min(-1)], 'field [kaarten] has an invalid min or max'],
    'a maximum of zero' => ['hero', fn (): array => [Items::make('kaarten', [Text::make('titel')])->max(0)], 'field [kaarten] has an invalid min or max'],
    'a minimum above the maximum' => ['hero', fn (): array => [Items::make('kaarten', [Text::make('titel')])->min(3)->max(2)], 'field [kaarten] has an invalid min or max'],
    'a choice without options' => ['hero', fn (): array => [Choice::make('stijl', [])], 'field [stijl] has no options'],
    'items without fields' => ['hero', fn (): array => [Items::make('kaarten', [])], 'field [kaarten] has no fields'],
    'items inside items' => ['hero', fn (): array => [Items::make('rijen', [Items::make('kaarten', [Text::make('titel')])])], 'field [kaarten] is a list inside a list'],
]);

it('allows the names type and visible inside an item', function (): void {
    $catalogue = new StripTypes([new InlineStripType('hero', [Items::make('kaarten', [Text::make('type'), Text::make('visible')])])]);

    expect($catalogue->has('hero'))->toBeTrue();
});

it('names the strip type class in a definition error', function (): void {
    expect(fn () => new StripTypes([new InlineStripType('hero', [Text::make('id')])]))
        ->toThrow(InvalidStripType::class, 'Strip type [Made\CmsPackage\Tests\Fixtures\Strips\InlineStripType] may not use the reserved field [id].');
});
