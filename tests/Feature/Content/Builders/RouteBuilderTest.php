<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Builders\RouteBuilder;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Route;

it('uses the custom builder for route queries', function (): void {
    expect(Route::query())->toBeInstanceOf(RouteBuilder::class);
});

it('finds the route at a path', function (): void {
    Page::factory()->at('/over-ons')->create();
    $team = Page::factory()->at('/over-ons/team')->create();

    $route = Route::query()->atPath(PagePath::fromString('over-ons/team'))->sole();

    expect($route->routable?->is($team))->toBeTrue();
});

it('finds the home route at the root', function (): void {
    $home = Page::factory()->at('/')->create();
    Page::factory()->at('/over-ons')->create();

    $route = Route::query()->atPath(PagePath::fromString(''))->sole();

    expect($route->routable?->is($home))->toBeTrue();
});
