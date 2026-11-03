<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\PageSlug;
use Made\CmsPackage\Content\Exceptions\InvalidPageSlug;

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
