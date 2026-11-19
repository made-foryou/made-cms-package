<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Models\Media;

beforeEach(function (): void {
    Storage::fake('public');
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

function namedPhoto(): Media
{
    return UploadMedia::run(new UploadedMedia(photo(400, 300), 'teamfoto.jpg'));
}

it('gives a file another name and answers with the file at its new address', function (): void {
    $media = namedPhoto();

    $response = $this->putJson('made-cms/media/'.$media->id.'/name', ['name' => 'Teamfoto Merwestad 4'])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($media->refresh()->name)->toBe('teamfoto-merwestad-4')
        ->and($response->json('file.name'))->toBe('teamfoto-merwestad-4')
        ->and($response->json('file.file'))->toBe('teamfoto-merwestad-4.webp')
        ->and($response->json('file.url'))->toBe(Storage::disk('public')->url($media->path));
});

it('refuses a name without letters or digits, or no text', function (mixed $name, string $message): void {
    $media = namedPhoto();

    $this->putJson('made-cms/media/'.$media->id.'/name', ['name' => $name])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', $message);

    expect($media->refresh()->name)->toBe('teamfoto');
})->with([
    'signs' => ['?!', 'Geef het bestand een naam met letters of cijfers.'],
    'empty' => ['', 'Geef het bestand een naam met letters of cijfers.'],
    'a list' => [['een'], 'Geef het bestand een naam met letters of cijfers.'],
]);

it('answers 404 for a file that is not in the library', function (): void {
    $this->putJson('made-cms/media/'.missingId().'/name', ['name' => 'teamfoto'])->assertNotFound();
});

it('opens for every editor who opens the pages or the media library', function (Role $role): void {
    $media = namedPhoto();

    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->putJson('made-cms/media/'.$media->id.'/name', ['name' => 'clubavond'])
        ->assertOk();
})->with([Role::Owner, Role::SeoSpecialist, Role::ContentManager]);

it('forbids it to an editor who opens neither and answers 401 to a guest', function (): void {
    $media = namedPhoto();

    $this->actingAs(Editor::factory()->withRoles()->create(), 'made-cms')
        ->putJson('made-cms/media/'.$media->id.'/name', ['name' => 'clubavond'])
        ->assertForbidden();

    auth('made-cms')->logout();

    $this->putJson('made-cms/media/'.$media->id.'/name', ['name' => 'clubavond'])->assertUnauthorized();

    expect($media->refresh()->name)->toBe('teamfoto');
});
