<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\RewritePagePaths;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Exceptions\PagePathTooLong;
use Made\CmsPackage\Content\Models\Page;

it('rewrites the address of a page and every page under it', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);
    $youth = Page::factory()->under($team)->at('/over-ons/team/jeugd')->create(['slug' => 'jeugd']);

    RewritePagePaths::run($about, PagePath::fromString('/vereniging'));

    expect($about->refresh()->route?->path)->toBe('/vereniging')
        ->and($team->refresh()->route?->path)->toBe('/vereniging/team')
        ->and($youth->refresh()->route?->path)->toBe('/vereniging/team/jeugd');
});

it('gives a page without an address one', function (): void {
    $page = Page::factory()->create(['slug' => 'los']);

    RewritePagePaths::run($page, PagePath::fromString('/los'));

    expect($page->refresh()->route?->path)->toBe('/los');
});

it('refuses an address too long for a page under it and writes nothing', function (string $to): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/'.str_repeat('t', 120))->create(['slug' => str_repeat('t', 120)]);

    expect(fn () => RewritePagePaths::run($about, PagePath::fromString($to)))->toThrow(PagePathTooLong::class);

    expect($about->refresh()->route?->path)->toBe('/over-ons')
        ->and($team->refresh()->route?->path)->toBe('/over-ons/'.str_repeat('t', 120));
})->with([
    'for the page itself' => ['/'.str_repeat('v', 120).'/'.str_repeat('w', 120).'/'.str_repeat('x', 13)],
    'for a page under it' => ['/'.str_repeat('v', 120).'/'.str_repeat('w', 13)],
]);
