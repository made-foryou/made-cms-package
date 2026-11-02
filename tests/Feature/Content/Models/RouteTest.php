<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Route;

it('stores the address of a page', function (): void {
    $page = Page::factory()->create();

    $route = $page->route()->create(['path' => '/over-ons']);

    expect(Route::query()->findOrFail($route->id)->path)->toBe('/over-ons');
});

it('points at its page through the cms_page alias', function (): void {
    $page = Page::factory()->at('/over-ons')->create();

    $route = Route::query()->sole();

    expect($route->routable_type)->toBe('cms_page')
        ->and($route->routable)->toBeInstanceOf(Page::class)
        ->and($route->routable?->is($page))->toBeTrue();
});

it('gives a page its address', function (): void {
    $page = Page::factory()->at('/over-ons/team')->create();

    expect($page->refresh()->route?->path)->toBe('/over-ons/team');
});

it('gives a draft page an address too', function (): void {
    $page = Page::factory()->at('/concept')->create();

    expect($page->route?->path)->toBe('/concept');
});

it('leaves a page without an address unless one is given', function (): void {
    $page = Page::factory()->create();

    expect($page->route)->toBeNull()
        ->and(Route::query()->count())->toBe(0);
});

it('refuses two routes at the same path', function (): void {
    Page::factory()->at('/over-ons')->create();

    Page::factory()->at('/over-ons')->create();
})->throws(QueryException::class);

it('refuses a second address for the same page', function (): void {
    $page = Page::factory()->at('/over-ons')->create();

    $page->route()->create(['path' => '/about']);
})->throws(QueryException::class);
