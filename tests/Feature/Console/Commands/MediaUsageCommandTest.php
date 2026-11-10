<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
});

it('builds the usage of media again from the strips of every page and says what it found', function (): void {
    $media = Media::factory()->create();
    Page::factory()->withStrips([storedStrip('hero', ['foto' => $media->id]), storedStrip('cards', ['kaarten' => [['icoon' => $media->id]]])])->create();
    Page::factory()->create();

    $this->artisan('made-cms:media-usage')
        ->expectsOutputToContain('Gebruik van media opnieuw opgebouwd uit 2 pagina’s: 2 plekken met een bestand.')
        ->assertSuccessful();

    expect(MediaUsage::query()->count())->toBe(2);
});

it('says when no file is in use', function (): void {
    Page::factory()->create();

    $this->artisan('made-cms:media-usage')
        ->expectsOutputToContain('Gebruik van media opnieuw opgebouwd uit 1 pagina: geen bestanden in gebruik.')
        ->assertSuccessful();
});
