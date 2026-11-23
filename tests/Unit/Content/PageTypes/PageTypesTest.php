<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Exceptions\DuplicatePageType;
use Made\CmsPackage\Content\Exceptions\InvalidPageType;
use Made\CmsPackage\Content\Exceptions\UnknownPageType;
use Made\CmsPackage\Content\PageTypes\PageType;
use Made\CmsPackage\Content\PageTypes\PageTypes;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Tests\Fixtures\PageTypes\InlinePageType;
use Made\CmsPackage\Tests\Fixtures\PageTypes\Valid\Nested\LandingPage;
use Made\CmsPackage\Tests\Fixtures\PageTypes\Valid\TeamPage;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Hero;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Nested\Cards;

const PAGE_TYPE_FIXTURES = __DIR__.'/../../../Fixtures/PageTypes';

function pageTypeStrips(): StripTypes
{
    return new StripTypes([new Hero, new Cards]);
}

it('finds the page types in the folder and its sub-folders', function (): void {
    $catalogue = PageTypes::discover(PAGE_TYPE_FIXTURES.'/Valid', 'Made\CmsPackage\Tests\Fixtures\PageTypes\Valid', pageTypeStrips());

    expect(array_map(static fn (PageType $type): string => $type::class, $catalogue->all()))
        ->toBe([LandingPage::class, TeamPage::class]);
});

it('is empty when the folder does not exist', function (): void {
    expect(PageTypes::discover(PAGE_TYPE_FIXTURES.'/Missing', 'App\Cms\PageTypes', pageTypeStrips())->all())->toBe([]);
});

it('finds a page type by its key', function (): void {
    $team = new TeamPage;
    $catalogue = new PageTypes([$team, new LandingPage], pageTypeStrips());

    expect($catalogue->find('team'))->toBe($team)
        ->and($catalogue->has('team'))->toBeTrue()
        ->and($catalogue->has('contact'))->toBeFalse();
});

it('lists the page types by label', function (): void {
    $catalogue = new PageTypes([new TeamPage, new InlinePageType('actie'), new LandingPage], pageTypeStrips());

    expect(array_map(static fn (PageType $type): string => $type->label(), $catalogue->all()))
        ->toBe(['Inline Page Type', 'Landing Page', 'Teampagina']);
});

it('refuses an unknown key', function (): void {
    expect(fn () => (new PageTypes([new TeamPage], pageTypeStrips()))->find('contact'))
        ->toThrow(UnknownPageType::class, 'Page type [contact] is not in the catalogue.');
});

it('refuses two page types with the same key', function (): void {
    expect(fn () => PageTypes::discover(PAGE_TYPE_FIXTURES.'/Duplicate', 'Made\CmsPackage\Tests\Fixtures\PageTypes\Duplicate', pageTypeStrips()))
        ->toThrow(DuplicatePageType::class, 'Page types [Made\CmsPackage\Tests\Fixtures\PageTypes\Duplicate\First] and [Made\CmsPackage\Tests\Fixtures\PageTypes\Duplicate\Second] share the key [team].');
});

it('refuses a definition that breaks the rules', function (InlinePageType $type, string $message): void {
    expect(fn () => new PageTypes([$type], pageTypeStrips()))
        ->toThrow(InvalidPageType::class, $message);
})->with([
    'a key with capitals' => [fn (): InlinePageType => new InlinePageType('Team'), 'has an invalid key [Team]'],
    'a key with a dot' => [fn (): InlinePageType => new InlinePageType('site.team'), 'has an invalid key [site.team]'],
    'no strip types' => [fn (): InlinePageType => new InlinePageType('team', []), 'allows no strip type'],
    'an unknown strip type' => [fn (): InlinePageType => new InlinePageType('team', ['hero', 'quote']), 'names the strip type [quote], which is not in the catalogue'],
    'an unknown default' => [fn (): InlinePageType => new InlinePageType('team', null, ['quote']), 'names the strip type [quote], which is not in the catalogue'],
    'a default it does not allow' => [fn (): InlinePageType => new InlinePageType('team', ['hero'], ['hero', 'cards']), 'starts with the strip type [cards], which it does not allow'],
]);

it('names the page type class in a definition error', function (): void {
    expect(fn () => new PageTypes([new InlinePageType('team', [])], pageTypeStrips()))
        ->toThrow(InvalidPageType::class, 'Page type [Made\CmsPackage\Tests\Fixtures\PageTypes\InlinePageType] allows no strip type.');
});
