<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Attention\MetaTitleSource;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'https://example.test', 'app.name' => 'TTV Merwestad']);
});

it('asks for a shorter title of its own once Google cuts it off', function (): void {
    $page = Page::factory()->published()->at('/locatie')->create(['meta_title' => str_repeat('a', 68)]);

    expect((new MetaTitleSource)->items($page))->toEqual([
        new AttentionItem(AttentionReason::MetaTitleTooLong, AttentionSeverity::Medium, ['length' => 68, 'limit' => 60]),
    ]);
});

it('measures the title Google gets without one of its own', function (): void {
    $page = Page::factory()->published()->at('/locatie')->create(['title' => str_repeat('b', 50)]);

    expect((new MetaTitleSource)->items($page))->toEqual([
        new AttentionItem(AttentionReason::MetaTitleTooLong, AttentionSeverity::Medium, ['length' => 66, 'limit' => 60]),
    ]);
});

it('leaves a title alone that Google shows whole', function (): void {
    $page = Page::factory()->published()->at('/locatie')->create(['meta_title' => str_repeat('a', 60)]);

    expect((new MetaTitleSource)->items($page))->toBe([]);
});

it('leaves the home page alone, whose title is the name of the site', function (): void {
    $home = Page::factory()->published()->at('/')->create(['title' => str_repeat('b', 80)]);

    expect((new MetaTitleSource)->items($home))->toBe([]);
});

it('leaves a page alone that Google does not show', function (Closure $page): void {
    expect((new MetaTitleSource)->items($page()))->toBe([]);
})->with([
    'never published' => [static fn (): Page => Page::factory()->create(['meta_title' => str_repeat('a', 68)])],
    'noindex' => [static fn (): Page => Page::factory()->published()->create(['meta_title' => str_repeat('a', 68), 'indexable' => false])],
    'hidden' => [static fn (): Page => Page::factory()->published()->hidden()->create(['meta_title' => str_repeat('a', 68)])],
]);
