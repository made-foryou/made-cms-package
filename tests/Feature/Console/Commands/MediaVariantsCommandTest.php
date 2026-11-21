<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Content\Models\Media;

beforeEach(function (): void {
    Storage::fake('public');
});

it('makes the missing narrower sizes and says how many photos had them already', function (): void {
    foreach (['media/01k1', 'media/01k2'] as $directory) {
        Storage::disk('public')->put("{$directory}/foto.webp", (string) file_get_contents(photo(1000, 600, 'webp')));
        Media::factory()->create(['path' => "{$directory}/foto.webp", 'width' => 1000, 'height' => 600]);
    }
    Media::factory()->withVariants()->create();

    $this->artisan('made-cms:media-variants')
        ->expectsOutputToContain('Formaten gemaakt voor 2 foto’s; 1 foto had ze al.')
        ->assertSuccessful();

    expect(Media::query()->withoutVariants()->count())->toBe(0);
});

it('says when there was nothing to make', function (): void {
    $this->artisan('made-cms:media-variants')
        ->expectsOutputToContain('Geen formaten gemaakt; geen foto had ze al.')
        ->assertSuccessful();
});

it('names a photo it could not give its sizes and fails', function (): void {
    $gone = Media::factory()->create(['name' => 'teamfoto', 'path' => 'media/01k1/teamfoto.webp']);
    Storage::disk('public')->put('media/01k2/kapot.webp', 'geen foto');
    $broken = Media::factory()->create(['name' => 'kapot', 'path' => 'media/01k2/kapot.webp']);

    $this->artisan('made-cms:media-variants')
        ->expectsOutputToContain('Geen formaten gemaakt; geen foto had ze al.')
        ->expectsOutputToContain("Geen formaten voor teamfoto (#{$gone->id}): het bestand staat niet op de media-disk.")
        ->expectsOutputToContain("Geen formaten voor kapot (#{$broken->id}): het bestand is geen foto die te lezen is.")
        ->assertFailed();
});
