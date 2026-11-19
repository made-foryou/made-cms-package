<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\SyncMediaUsage;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    Storage::fake('public');
    $this->editor = Editor::factory()->create();
    $this->actingAs($this->editor, 'made-cms');
});

function uploadedReplacement(string $path, string $name): UploadedFile
{
    return new UploadedFile($path, $name, null, null, true);
}

it('takes a file as the new version and answers with the file as it is stored now', function (): void {
    $media = UploadMedia::run(new UploadedMedia(photo(800, 600), 'teamfoto.jpg'));

    $response = $this->post("made-cms/media/{$media->id}/replace", ['file' => uploadedReplacement(photo(640, 480, 'png'), 'Teamfoto 2026.png')], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    $stored = Media::query()->findOrFail($media->id);

    expect($response->json('file.id'))->toBe($media->id)
        ->and($response->json('file.name'))->toBe('teamfoto-2026')
        ->and($response->json('file.url'))->toBe(Storage::disk('public')->url($stored->path))
        ->and($stored->version)->toBe(2)
        ->and($stored->replaced_by)->toBe($this->editor->id)
        ->and($response->json('notice'))->toBe('Vervangen door teamfoto-2026.webp.');
});

it('tells that the site shows the new version when a published page holds the file', function (string $version, string $notice): void {
    useStripTypes();
    $media = UploadMedia::run(new UploadedMedia(photo(800, 600), 'teamfoto.jpg'));
    $strips = [storedStrip('hero', ['foto' => $media->id], id: 'h')];
    $page = ($version === 'draft' ? Page::factory()->withStrips([])->withDraft($strips) : Page::factory()->withStrips($strips))->create();
    SyncMediaUsage::run($page);

    $this->post("made-cms/media/{$media->id}/replace", ['file' => uploadedReplacement(photo(640, 480), 'Teamfoto 2026.jpg')], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('notice', $notice);
})->with([
    'on the site' => ['published', 'Vervangen door teamfoto-2026.webp. Op de site staat nu de nieuwe versie.'],
    'only in a draft' => ['draft', 'Vervangen door teamfoto-2026.webp.'],
]);

it('tells that the site shows the new version when a published page shares the file', function (string $state, string $notice): void {
    $media = UploadMedia::run(new UploadedMedia(photo(800, 600), 'teamfoto.jpg'));
    $page = match ($state) {
        'published' => Page::factory()->published(),
        'draft' => Page::factory(),
        'deleted' => Page::factory()->published()->state(['deleted_at' => now()]),
    };
    SyncMediaUsage::run($page->withShareImage($media)->create());

    $this->post("made-cms/media/{$media->id}/replace", ['file' => uploadedReplacement(photo(640, 480), 'Teamfoto 2026.jpg')], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('notice', $notice);
})->with([
    'a published page' => ['published', 'Vervangen door teamfoto-2026.webp. Op de site staat nu de nieuwe versie.'],
    'a page that was never published' => ['draft', 'Vervangen door teamfoto-2026.webp.'],
    'a page in the bin' => ['deleted', 'Vervangen door teamfoto-2026.webp.'],
]);

it('refuses a file of another kind, keyed by file', function (): void {
    $media = UploadMedia::run(new UploadedMedia(photo(800, 600), 'teamfoto.jpg'));

    $this->post("made-cms/media/{$media->id}/replace", ['file' => uploadedReplacement(mediaFixture('wedstrijdschema.pdf'), 'schema.pdf')], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.file.0', 'Kies een afbeelding: je vervangt een afbeelding.');
});

it('refuses a request without a file', function (): void {
    $media = Media::factory()->create();

    $this->postJson("made-cms/media/{$media->id}/replace")
        ->assertUnprocessable()
        ->assertJsonPath('errors.file.0', 'Kies een bestand om te uploaden.');
});

it('answers 404 for a file that is gone', function (): void {
    $this->post('made-cms/media/'.missingId().'/replace', ['file' => uploadedReplacement(photo(80, 60), 'a.jpg')], ['Accept' => 'application/json'])
        ->assertNotFound();
});

it('opens only for an editor who opens the media library', function (Role $role, int $status): void {
    $media = UploadMedia::run(new UploadedMedia(photo(80, 60), 'a.jpg'));

    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->post("made-cms/media/{$media->id}/replace", ['file' => uploadedReplacement(photo(80, 60), 'b.jpg')], ['Accept' => 'application/json'])
        ->assertStatus($status);
})->with([
    'content manager' => [Role::ContentManager, 200],
    'owner' => [Role::Owner, 403],
    'seo specialist' => [Role::SeoSpecialist, 403],
]);

it('refuses an editor without roles and a guest', function (): void {
    $media = Media::factory()->create();

    $this->actingAs(Editor::factory()->withRoles()->create(), 'made-cms')
        ->postJson("made-cms/media/{$media->id}/replace")
        ->assertForbidden();

    auth('made-cms')->logout();

    $this->postJson("made-cms/media/{$media->id}/replace")->assertUnauthorized();
});
