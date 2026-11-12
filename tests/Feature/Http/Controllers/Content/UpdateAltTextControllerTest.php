<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Media;

beforeEach(function (): void {
    Storage::fake('public');
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('gives a file its alt text and answers with the file', function (): void {
    $media = Media::factory()->create(['name' => 'clubavond-oktober']);

    $response = $this->putJson('made-cms/media/'.$media->id.'/alt', ['alt' => '  Leden aan de bar na de clubavond in oktober '])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($media->refresh()->alt)->toBe('Leden aan de bar na de clubavond in oktober')
        ->and($response->json('file.id'))->toBe($media->id)
        ->and($response->json('file.alt'))->toBe('Leden aan de bar na de clubavond in oktober')
        ->and($response->json('file.image.alt'))->toBe('Leden aan de bar na de clubavond in oktober')
        ->and($response->json('file.thumb'))->toBe(Storage::disk('public')->url((string) $media->thumb_path));
});

it('takes the alt text away with a blank one', function (mixed $alt): void {
    $media = Media::factory()->withAlt('Oude tekst')->create();

    $this->putJson('made-cms/media/'.$media->id.'/alt', ['alt' => $alt])
        ->assertOk()
        ->assertJsonPath('file.alt', null);

    expect($media->refresh()->alt)->toBeNull();
})->with(['empty' => [''], 'spaces' => ['   '], 'null' => [null]]);

it('refuses an alt text that is too long or no text', function (mixed $alt, string $message): void {
    $media = Media::factory()->withAlt('Oude tekst')->create();

    $this->putJson('made-cms/media/'.$media->id.'/alt', ['alt' => $alt])
        ->assertUnprocessable()
        ->assertJsonPath('errors.alt.0', $message);

    expect($media->refresh()->alt)->toBe('Oude tekst');
})->with([
    'too long' => [str_repeat('a', 256), 'Een alt-tekst mag hoogstens 255 tekens zijn.'],
    'a list' => [['een', 'twee'], 'Een alt-tekst is gewone tekst.'],
]);

it('answers 404 for a file that is not in the library', function (string $id): void {
    $this->putJson('made-cms/media/'.$id.'/alt', ['alt' => 'Tekst'])->assertNotFound();
})->with(['gone' => ['999'], 'no number' => ['teamfoto']]);

it('opens for every editor who opens the pages or the media library', function (Role $role): void {
    $media = Media::factory()->create();

    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->putJson('made-cms/media/'.$media->id.'/alt', ['alt' => 'Tekst'])
        ->assertOk();
})->with([Role::Owner, Role::SeoSpecialist, Role::ContentManager]);

it('forbids it to an editor who opens neither and answers 401 to a guest', function (): void {
    $media = Media::factory()->create();

    $this->actingAs(Editor::factory()->withRoles()->create(), 'made-cms')
        ->putJson('made-cms/media/'.$media->id.'/alt', ['alt' => 'Tekst'])
        ->assertForbidden();

    auth('made-cms')->logout();

    $this->putJson('made-cms/media/'.$media->id.'/alt', ['alt' => 'Tekst'])->assertUnauthorized();

    expect($media->refresh()->alt)->toBeNull();
});
