<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\PageVersion;

it('removes pages deleted more than the kept days ago and says how many', function (): void {
    $old = Page::factory()->deletedAt(now()->subDays(31))->create();
    $recent = Page::factory()->deletedAt(now()->subDays(29))->create();

    $this->artisan('made-cms:prune')
        ->expectsOutputToContain('1 pagina definitief verwijderd.')
        ->assertSuccessful();

    expect(Page::query()->withTrashed()->find($old->id))->toBeNull()
        ->and(Page::query()->withTrashed()->find($recent->id))->not->toBeNull();
});

it('removes the versions of a page past the kept number and says how many', function (): void {
    config(['made-cms-package.pages.keep_versions' => 2]);
    $page = Page::factory()->create();
    PageVersion::factory()->for($page)->count(5)->sequence(fn ($sequence): array => ['number' => $sequence->index + 1])->create();

    $this->artisan('made-cms:prune')
        ->expectsOutputToContain('3 oudere versies opgeruimd.')
        ->assertSuccessful();

    expect(PageVersion::query()->ofPage($page)->count())->toBe(2);
});

it('says when there was nothing to remove', function (): void {
    $this->artisan('made-cms:prune')
        ->expectsOutputToContain('Geen pagina’s definitief verwijderd.')
        ->expectsOutputToContain('Geen oudere versies opgeruimd.')
        ->assertSuccessful();
});
