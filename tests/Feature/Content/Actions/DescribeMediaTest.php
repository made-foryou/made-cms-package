<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Content\Actions\DescribeMedia;
use Made\CmsPackage\Content\Actions\SyncMediaUsage;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    Storage::fake('public');
    useStripTypes();
});

it('describes a file with the url a visitor opens it at and the places it is used', function (): void {
    $media = Media::factory()->in(MediaFolder::factory()->create(['name' => 'Teams']))->create(['name' => 'teamfoto']);
    $page = Page::factory()->withStrips([storedStrip('hero', ['foto' => $media->id], id: 'h')])->create(['title' => 'Onze teams']);
    SyncMediaUsage::run($page);

    $description = DescribeMedia::run($media);

    expect($description->file->id)->toBe($media->id)
        ->and($description->file->folder)->toBe('Teams')
        ->and($description->file->url)->toEndWith('/'.$media->path)
        ->and($description->places->count())->toBe(1)
        ->and($description->places->places[0]->where)->toBe('Hero › Foto')
        ->and($description->places->places[0]->pageHasDraft)->toBeFalse();
});

it('describes a file used nowhere with no places', function (): void {
    $media = Media::factory()->document()->create();

    expect(DescribeMedia::run($media)->places->isEmpty())->toBeTrue();
});

it('says of a place whether its page has a draft', function (): void {
    $media = Media::factory()->create();
    $strip = storedStrip('hero', ['foto' => $media->id], id: 'h');
    $page = Page::factory()->published()->withStrips([$strip])->withDraft([])->create();
    SyncMediaUsage::run($page);

    expect(DescribeMedia::run($media)->places->places[0]->pageHasDraft)->toBeTrue();
});
