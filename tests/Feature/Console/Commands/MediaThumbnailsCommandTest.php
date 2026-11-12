<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Content\Models\Media;

beforeEach(function (): void {
    Storage::fake('public');
});

it('makes the missing thumbnails and says how many photos had one already', function (): void {
    foreach (['media/01k1', 'media/01k2'] as $directory) {
        Storage::disk('public')->put("{$directory}/foto.webp", (string) file_get_contents(photo(800, 600, 'webp')));
        Media::factory()->withoutThumbnail()->create(['path' => "{$directory}/foto.webp"]);
    }
    Media::factory()->create();

    $this->artisan('made-cms:media-thumbnails')
        ->expectsOutputToContain('2 miniaturen gemaakt; 1 foto had er al een.')
        ->assertSuccessful();

    expect(Media::query()->withoutThumbnail()->count())->toBe(0);
});

it('says when there was nothing to make', function (): void {
    $this->artisan('made-cms:media-thumbnails')
        ->expectsOutputToContain('Geen miniaturen gemaakt; geen foto had er al een.')
        ->assertSuccessful();
});

it('names a photo it could not give a thumbnail and fails', function (): void {
    $gone = Media::factory()->withoutThumbnail()->create(['name' => 'teamfoto', 'path' => 'media/01k1/teamfoto.webp']);
    Storage::disk('public')->put('media/01k2/kapot.webp', 'geen foto');
    $broken = Media::factory()->withoutThumbnail()->create(['name' => 'kapot', 'path' => 'media/01k2/kapot.webp']);

    $this->artisan('made-cms:media-thumbnails')
        ->expectsOutputToContain('Geen miniaturen gemaakt; geen foto had er al een.')
        ->expectsOutputToContain("Geen miniatuur voor teamfoto (#{$gone->id}): het bestand staat niet op de media-disk.")
        ->expectsOutputToContain("Geen miniatuur voor kapot (#{$broken->id}): het bestand is geen foto die te lezen is.")
        ->assertFailed();
});
