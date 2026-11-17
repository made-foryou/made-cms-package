<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;

beforeEach(function (): void {
    Storage::fake('public');
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('deletes the files no page uses and says what it did in words', function (): void {
    [$one, $two, $used] = Media::factory()->count(3)->create()->all();
    MediaUsage::factory()->for($used)->create();

    $this->postJson('made-cms/media/delete', ['files' => [$one->id, $two->id, $used->id, 999]])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson([
            'deleted' => [$one->id, $two->id],
            'kept' => [$used->id],
            'missing' => [999],
            'notice' => '2 bestanden verwijderd.',
            'refusal' => basename($used->path).' staat nog op een pagina en blijft staan.',
        ]);

    expect(Media::query()->pluck('id')->all())->toBe([$used->id]);
});

it('names a single deleted file, and the files it kept in one list', function (): void {
    $free = Media::factory()->create(['name' => 'teamfoto']);
    [$logo, $schema] = Media::factory()->count(2)->create()->all();
    MediaUsage::factory()->for($logo)->create();
    MediaUsage::factory()->for($schema)->create();

    $this->postJson('made-cms/media/delete', ['files' => [$free->id, $logo->id, $schema->id]])
        ->assertOk()
        ->assertJsonPath('notice', 'teamfoto.webp is verwijderd.')
        ->assertJsonPath('refusal', basename($logo->path).' en '.basename($schema->path).' staan nog op een pagina en blijven staan.');
});

it('tells nothing when nothing was deleted or kept', function (): void {
    $this->postJson('made-cms/media/delete', ['files' => [999]])
        ->assertOk()
        ->assertJsonPath('notice', null)
        ->assertJsonPath('refusal', null)
        ->assertJsonPath('missing', [999]);
});

it('refuses a list of files it cannot take', function (mixed $files): void {
    $this->postJson('made-cms/media/delete', ['files' => $files])
        ->assertUnprocessable()
        ->assertJsonPath('errors', fn (array $errors): bool => collect($errors)->flatten()->unique()->values()->all() === ['Kies de bestanden die je wilt verwijderen. Herlaad de pagina en probeer het opnieuw.']);
})->with([
    'none' => [[]],
    'not a list' => ['1,2'],
    'a text in it' => [[1, 'twee']],
    'twice the same' => [[1, 1]],
    'more than 200' => [range(1, 201)],
]);

it('opens only for an editor who opens the media library', function (Role $role, int $status): void {
    $media = Media::factory()->create();

    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->postJson('made-cms/media/delete', ['files' => [$media->id]])
        ->assertStatus($status);
})->with([
    'content manager' => [Role::ContentManager, 200],
    'owner' => [Role::Owner, 403],
    'seo specialist' => [Role::SeoSpecialist, 403],
]);

it('refuses an editor without roles and a guest', function (): void {
    $this->actingAs(Editor::factory()->withRoles()->create(), 'made-cms')
        ->postJson('made-cms/media/delete', ['files' => [1]])
        ->assertForbidden();

    auth('made-cms')->logout();

    $this->postJson('made-cms/media/delete', ['files' => [1]])->assertUnauthorized();
});
