<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\ListAllowedStripTypes;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Content\Strips\StripType;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Tests\Fixtures\PageTypes\InlinePageType;
use Made\CmsPackage\Tests\Fixtures\Strips\InlineStripType;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Hero;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Nested\Cards;

beforeEach(function (): void {
    app()->instance(StripTypes::class, new StripTypes([new Hero, new Cards, new InlineStripType('quote', [Text::make('quote')])]));
    usePageTypesWith(new InlinePageType('team', ['quote', 'hero']));
});

/**
 * @param  list<StripType>  $types
 * @return list<string>
 */
function allowedKeys(array $types): array
{
    return array_map(static fn (StripType $type): string => $type->key(), $types);
}

it('allows every strip type on an ordinary page', function (): void {
    expect(allowedKeys(ListAllowedStripTypes::run(null)))->toBe(['hero', 'quote', 'cards']);
});

it('allows only the strip types of the type of the page, in the order of the catalogue', function (): void {
    expect(allowedKeys(ListAllowedStripTypes::run('team')))->toBe(['hero', 'quote']);
});

it('allows every strip type on a page whose type the site no longer defines', function (): void {
    expect(allowedKeys(ListAllowedStripTypes::run('vervallen')))->toBe(['hero', 'quote', 'cards']);
});
