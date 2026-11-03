<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Models\Page;

it('removes pages deleted more than the kept days ago and says how many', function (): void {
    $old = Page::factory()->deletedAt(now()->subDays(31))->create();
    $recent = Page::factory()->deletedAt(now()->subDays(29))->create();

    $this->artisan('made-cms:prune')
        ->expectsOutputToContain('1 pagina definitief verwijderd.')
        ->assertSuccessful();

    expect(Page::query()->withTrashed()->find($old->id))->toBeNull()
        ->and(Page::query()->withTrashed()->find($recent->id))->not->toBeNull();
});

it('says when there was nothing to remove', function (): void {
    $this->artisan('made-cms:prune')
        ->expectsOutputToContain('Geen pagina’s definitief verwijderd.')
        ->assertSuccessful();
});
