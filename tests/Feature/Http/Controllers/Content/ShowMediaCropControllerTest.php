<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\CropImage;
use Made\CmsPackage\Content\Actions\SyncMediaUsage;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\CropArea;
use Made\CmsPackage\Content\Data\CropMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    Storage::fake('public');
    useStripTypes();
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('describes a photo for the crop step, never the place of its files', function (): void {
    CarbonImmutable::setTestNow('2026-10-09 14:00:00');
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    $media = UploadMedia::run(new UploadedMedia(quadrants(1200, 800), 'Teamfoto Merwestad 4.jpg', $folder->id));
    $size = Storage::disk('public')->size((string) $media->original_path);

    $response = $this->getJson('made-cms/media/'.$media->id.'/crop')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($response->json())->toMatchArray([
        'folder' => $folder->id,
        'original' => [
            'url' => url('made-cms/media/'.$media->id.'/original'),
            'width' => 1200,
            'height' => 800,
            'size' => $size,
            'extension' => 'jpg',
        ],
        'crop' => ['x' => 0, 'y' => 0, 'width' => 1200, 'height' => 800, 'rotation' => 0, 'flip' => false],
        'scale_down' => true,
        'pages' => 0,
        'used' => null,
        'line' => 'teamfoto-merwestad-4.jpg · 1200 × 800 · '.max(1, (int) round($size / 1000)).' kB · geüpload 9 oktober 2026',
    ])
        ->and($response->json('file.id'))->toBe($media->id)
        ->and($response->json('file.croppable'))->toBeTrue()
        ->and((string) $response->getContent())->not->toContain('original-');
});

it('describes a cropped photo by its crop', function (): void {
    $media = UploadMedia::run(new UploadedMedia(quadrants(1200, 800), 'teamfoto.jpg'));
    CropImage::run($media, new CropMedia(new CropArea(0, 0, 400, 600, 90, true), scaleDown: false));

    $this->getJson('made-cms/media/'.$media->id.'/crop')
        ->assertOk()
        ->assertJsonPath('crop', ['x' => 0, 'y' => 0, 'width' => 400, 'height' => 600, 'rotation' => 90, 'flip' => true])
        ->assertJsonPath('scale_down', false);
});

it('says on how many pages the photo changes', function (int $count, string $used): void {
    $media = Media::factory()->create();
    Page::factory()->count($count)->withStrips([storedStrip('hero', ['foto' => $media->id])])->create()
        ->each(fn (Page $page) => SyncMediaUsage::run($page));

    $this->getJson('made-cms/media/'.$media->id.'/crop')
        ->assertOk()
        ->assertJsonPath('pages', $count)
        ->assertJsonPath('used', $used);
})->with([
    'one' => [1, 'Staat op 1 pagina. Bijsnijden verandert hem daar meteen, ook op de gepubliceerde site.'],
    'two' => [2, "Staat op 2 pagina's. Bijsnijden verandert hem daar meteen, ook op de gepubliceerde site."],
]);

it('leaves out the size of an original the disk cannot tell', function (): void {
    $media = Media::factory()->create(['name' => 'teamfoto', 'original_path' => 'media/01k9/original-weg.jpg']);

    $this->getJson('made-cms/media/'.$media->id.'/crop')
        ->assertOk()
        ->assertJsonPath('original.size', null)
        ->assertJsonPath('line', 'teamfoto.jpg · 1920 × 1280 · geüpload '.now()->locale('nl')->isoFormat('D MMMM YYYY'));
});

it('answers 404 for a file that cannot be cropped or is not in the library', function (Closure $id): void {
    $this->getJson('made-cms/media/'.$id().'/crop')->assertNotFound();
})->with([
    'a document' => [fn (): int => Media::factory()->document()->create()->id],
    'gone' => [fn (): string => '999'],
    'no number' => [fn (): string => 'teamfoto'],
]);

it('opens for every editor who opens the pages or the media library', function (Role $role): void {
    $media = Media::factory()->create();

    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->getJson('made-cms/media/'.$media->id.'/crop')
        ->assertOk();
})->with([Role::Owner, Role::SeoSpecialist, Role::ContentManager]);

it('forbids it to an editor who opens neither and answers 401 to a guest', function (): void {
    $media = Media::factory()->create();

    $this->actingAs(Editor::factory()->withRoles()->create(), 'made-cms')
        ->getJson('made-cms/media/'.$media->id.'/crop')
        ->assertForbidden();

    auth('made-cms')->logout();

    $this->getJson('made-cms/media/'.$media->id.'/crop')->assertUnauthorized();
});
