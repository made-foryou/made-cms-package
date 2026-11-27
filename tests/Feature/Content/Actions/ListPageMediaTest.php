<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Content\Actions\ListPageMedia;
use Made\CmsPackage\Content\Actions\SyncMediaUsage;
use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Content\Data\PageMedia;
use Made\CmsPackage\Content\Exceptions\MediaDiskNotUsable;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
    Storage::fake('public', ['url' => 'https://cdn.example.com']);
});

/**
 * The ids of the files of a page as the card lists them.
 *
 * @return list<int>
 */
function pageMediaIds(PageMedia $media): array
{
    return array_map(static fn (MediaFile $file): int => $file->id, $media->files);
}

it('lists the files of the draft while there is one, each once and in the order of the page, a hidden strip too', function (): void {
    [$published, $icon, $photo] = Media::factory()->count(3)->create()->all();
    $page = Page::factory()->published()
        ->withStrips([storedStrip('hero', ['foto' => $published->id], id: 'h')])
        ->withDraft([
            storedStrip('cards', ['kaarten' => [['id' => 'k1', 'titel' => 'Een', 'icoon' => $icon->id]]], id: 'c'),
            storedStrip('hero', ['foto' => $photo->id], visible: false, id: 'h'),
            storedStrip('hero', ['foto' => $icon->id], id: 'h2'),
        ])
        ->create();
    SyncMediaUsage::run($page);

    $media = ListPageMedia::run($page);

    expect(pageMediaIds($media))->toBe([$icon->id, $photo->id])
        ->and($media->files[0]->thumb)->toBe("https://cdn.example.com/{$icon->thumb_path}")
        ->and($media->available)->toBeTrue();
});

it('lists the published files of a page without a draft', function (): void {
    $photo = Media::factory()->create();
    $page = Page::factory()->published()->withStrips([storedStrip('hero', ['foto' => $photo->id])])->create();
    SyncMediaUsage::run($page);

    expect(pageMediaIds(ListPageMedia::run($page)))->toBe([$photo->id]);
});

it('leaves the share image out: it lies outside the strips of the working copy', function (): void {
    [$share, $photo] = Media::factory()->count(2)->create()->all();
    $page = Page::factory()->published()->withShareImage($share)
        ->withStrips([storedStrip('hero', ['foto' => $photo->id])])
        ->create();
    SyncMediaUsage::run($page);

    expect(pageMediaIds(ListPageMedia::run($page)))->toBe([$photo->id]);
});

it('says which images of the working copy have no alt text, in the order of the page; a document never needs one', function (): void {
    $described = Media::factory()->withAlt('Het eerste team')->create();
    $document = Media::factory()->document()->create();
    [$first, $second] = Media::factory()->count(2)->create()->all();
    $onlyPublished = Media::factory()->create();
    $page = Page::factory()->published()
        ->withStrips([storedStrip('hero', ['foto' => $onlyPublished->id], id: 'h0')])
        ->withDraft([
            storedStrip('hero', ['foto' => $described->id], id: 'h1'),
            storedStrip('hero', ['foto' => $second->id], id: 'h2'),
            storedStrip('cards', ['kaarten' => [['id' => 'k1', 'icoon' => $document->id], ['id' => 'k2', 'icoon' => $first->id]]], id: 'c'),
        ])
        ->create();
    SyncMediaUsage::run($page);

    $media = ListPageMedia::run($page);

    expect(pageMediaIds($media))->toBe([$described->id, $second->id, $document->id, $first->id])
        ->and($media->withoutAlt)->toBe([$second->id, $first->id]);
});

it('asks nothing of the media disk for a page without files', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip('hero', ['kop' => 'Welkom'])])->create();
    config(['made-cms-package.media.disk' => 'missing']);

    expect(ListPageMedia::run($page))->toEqual(new PageMedia);
});

it('reports a media disk a visitor cannot open and shows no files, so the screen of the page keeps working', function (): void {
    Exceptions::fake();
    $photo = Media::factory()->create();
    $page = Page::factory()->published()->withStrips([storedStrip('hero', ['foto' => $photo->id])])->create();
    SyncMediaUsage::run($page);
    config(['made-cms-package.media.disk' => 'missing']);

    expect(ListPageMedia::run($page))->toEqual(new PageMedia(available: false));
    Exceptions::assertReported(MediaDiskNotUsable::class);
});
