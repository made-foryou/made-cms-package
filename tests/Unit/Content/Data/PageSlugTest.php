<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\PageSlug;
use Made\CmsPackage\Content\Exceptions\InvalidPageSlug;
use Made\CmsPackage\Content\Exceptions\PageSlugTooLong;

it('slugs a title', function (string $raw, string $expected): void {
    expect(PageSlug::fromString($raw)->value)->toBe($expected);
})->with([
    'words' => ['Over ons', 'over-ons'],
    'accents and symbols' => ['Café & Bar', 'cafe-bar'],
    'surrounding whitespace' => [' Team ', 'team'],
    'underscore' => ['Over_Ons', 'over-ons'],
    'punctuation' => ['Ons Team!', 'ons-team'],
]);

it('keeps a slug that already is one', function (): void {
    expect(PageSlug::fromString('over-ons-2')->value)->toBe('over-ons-2');
});

it('refuses a slug with nothing left', function (string $raw): void {
    expect(fn (): PageSlug => PageSlug::fromString($raw))->toThrow(InvalidPageSlug::class);
})->with(['empty' => [''], 'whitespace' => ['   '], 'symbols' => ['???']]);

it('keeps a slug of the longest length', function (): void {
    expect(PageSlug::fromString(str_repeat('a', PageSlug::MAX_LENGTH))->value)->toBe(str_repeat('a', 120));
});

it('refuses a slug that is longer than the longest length once it is made', function (string $raw): void {
    expect(fn (): PageSlug => PageSlug::fromString($raw))->toThrow(PageSlugTooLong::class);
})->with([
    'too many letters' => [str_repeat('a', 121)],
    'letters that grow in an address' => [str_repeat('щ', 41)],
]);
