<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Attention\MetaDescriptionLengthSource;
use Made\CmsPackage\Content\Data\AttentionContext;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'https://example.test', 'app.name' => 'TTV Merwestad']);
});

it('asks for a shorter description once Google cuts it off', function (): void {
    $page = Page::factory()->published()->at('/locatie')->withMeta(null, str_repeat('a', 156))->create();

    expect((new MetaDescriptionLengthSource)->items($page, new AttentionContext))->toEqual([
        new AttentionItem(AttentionReason::MetaDescriptionTooLong, AttentionSeverity::Medium, ['length' => 156, 'limit' => 155]),
    ]);
});

it('leaves a description alone that Google shows whole or that is missing', function (?string $description): void {
    $page = Page::factory()->published()->at('/locatie')->withMeta(null, $description)->create();

    expect((new MetaDescriptionLengthSource)->items($page, new AttentionContext))->toBe([]);
})->with([
    'whole' => [str_repeat('a', 155)],
    'missing' => [null],
]);

it('leaves a page alone that Google does not show', function (Closure $page): void {
    expect((new MetaDescriptionLengthSource)->items($page(), new AttentionContext))->toBe([]);
})->with([
    'never published' => [static fn (): Page => Page::factory()->withMeta(null, str_repeat('a', 156))->create()],
    'noindex' => [static fn (): Page => Page::factory()->published()->noindex()->withMeta(null, str_repeat('a', 156))->create()],
    'hidden' => [static fn (): Page => Page::factory()->published()->hidden()->withMeta(null, str_repeat('a', 156))->create()],
]);
