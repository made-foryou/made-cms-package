<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Strip;

beforeEach(function (): void {
    $this->content = useContentFolders();
});

afterEach(function (): void {
    File::deleteDirectory($this->content);
});

it('tells the edit mode the type and label of the strips on a page', function (): void {
    $hero = Strip::factory()->create(['type' => 'hero']);
    $figures = Strip::factory()->create(['type' => 'cijfers']);

    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->getJson('made-cms/strips/describe?'.http_build_query(['ids' => [$figures->id, $hero->id, 999]]))
        ->assertOk()
        ->assertExactJson(['strips' => [
            ['id' => $hero->id, 'type' => 'hero', 'label' => 'Hero'],
            ['id' => $figures->id, 'type' => 'cijfers', 'label' => 'Cijfers'],
        ]]);
});

it('answers every role, because every role opens the pages', function (Role $role): void {
    $strip = Strip::factory()->create();

    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->getJson('made-cms/strips/describe?ids[]='.$strip->id)
        ->assertOk();
})->with(Role::cases());

it('turns a guest away', function (): void {
    $strip = Strip::factory()->create();

    $this->getJson('made-cms/strips/describe?ids[]='.$strip->id)->assertUnauthorized();
});

it('refuses a request without valid ids', function (string $query): void {
    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->getJson('made-cms/strips/describe'.$query)
        ->assertUnprocessable();
})->with([
    'no ids' => [''],
    'an id that is not a number' => ['?ids[]=hero'],
    'an id below one' => ['?ids[]=0'],
    'more than a hundred ids' => ['?'.http_build_query(['ids' => range(1, 101)])],
]);

it('is never stored by a cache', function (): void {
    $strip = Strip::factory()->create();

    $response = $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->getJson('made-cms/strips/describe?ids[]='.$strip->id);

    expect($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
});
