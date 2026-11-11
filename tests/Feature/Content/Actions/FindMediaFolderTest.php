<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\FindMediaFolder;
use Made\CmsPackage\Content\Models\MediaFolder;

it('finds a folder by its id', function (): void {
    $teams = MediaFolder::factory()->create(['name' => 'Teams']);

    expect(FindMediaFolder::run($teams->id)?->is($teams))->toBeTrue();
});

it('finds no folder without an id or for a folder that is gone', function (?int $id): void {
    expect(FindMediaFolder::run($id))->toBeNull();
})->with(['no id' => [null], 'gone' => [404]]);
