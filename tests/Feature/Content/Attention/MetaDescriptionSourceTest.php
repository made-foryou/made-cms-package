<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Attention\MetaDescriptionSource;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Models\Page;

it('asks for a description Google shows a page without', function (?string $description): void {
    $page = Page::factory()->published()->create(['meta_description' => $description]);

    expect((new MetaDescriptionSource)->items($page))->toEqual([
        new AttentionItem(AttentionReason::MetaDescriptionMissing, AttentionSeverity::High),
    ]);
})->with([null, '', '   ']);

it('leaves a page with a description alone', function (): void {
    $page = Page::factory()->published()->create(['meta_description' => 'Alle teams van de club.']);

    expect((new MetaDescriptionSource)->items($page))->toBe([]);
});

it('leaves a page alone that Google does not show', function (Closure $page): void {
    expect((new MetaDescriptionSource)->items($page()))->toBe([]);
})->with([
    'never published' => [static fn (): Page => Page::factory()->create()],
    'noindex' => [static fn (): Page => Page::factory()->published()->create(['indexable' => false])],
    'hidden' => [static fn (): Page => Page::factory()->published()->hidden()->create()],
]);
