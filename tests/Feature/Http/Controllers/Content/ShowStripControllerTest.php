<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Tests\Fixtures\Strips\Downloads;

beforeEach(function (): void {
    useStripTypes();
});

it('answers at an address under the pages of the cms', function (): void {
    $page = Page::factory()->create();

    expect(route('made-cms-package.pages.strips.show', ['page' => $page, 'strip' => 'a'], false))
        ->toBe('/made-cms/pages/'.$page->id.'/strips/a');
});

it('hands the side panel the fields and the draft values of a strip', function (): void {
    $page = Page::factory()->published()
        ->withStrips([storedStrip('hero', ['kop' => 'Live'], id: 'a')])
        ->withDraft([storedStrip('hero', ['kop' => 'Concept'], id: 'a')])
        ->create();

    $response = $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->getJson('made-cms/pages/'.$page->id.'/strips/a')
        ->assertOk()
        ->assertJsonPath('strip.id', 'a')
        ->assertJsonPath('strip.type', 'hero')
        ->assertJsonPath('strip.label', 'Hero')
        ->assertJsonPath('strip.visible', true)
        ->assertJsonPath('strip.values.kop', 'Concept')
        ->assertJsonPath('strip.fields.1.key', 'kop')
        ->assertJsonPath('strip.fields.1.required', true)
        ->assertJsonPath('strip.fields.5.type', 'items')
        ->assertJsonPath('strip.fields.5.fields.2.options.0', ['value' => 'primary', 'label' => 'Primair']);

    expect($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
});

it('hands the side panel the files of the images of the strip, by their url and never their place on the disk', function (): void {
    Storage::fake('public');
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    $photo = Media::factory()->in($folder)->withAlt('Het eerste team')->create([
        'name' => 'teamfoto',
        'path' => 'media/01k9/teamfoto.webp',
        'original_path' => 'media/01k9/original-geheim123.jpg',
        'thumb_path' => 'media/01k9/thumb-klein.webp',
        'size' => 412_000,
        'width' => 1600,
        'height' => 1067,
    ]);
    $page = Page::factory()->withDraft([storedStrip('hero', ['foto' => $photo->id], id: 'a')])->create();

    $response = $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->getJson('made-cms/pages/'.$page->id.'/strips/a')
        ->assertOk()
        ->assertJsonPath('strip.values.foto', $photo->id)
        ->assertJsonPath('strip.fields.6', [
            'key' => 'foto',
            'type' => 'image',
            'label' => 'Foto',
            'hint' => 'Minimaal 1600 px breed.',
            'required' => false,
            'default' => null,
        ]);

    $url = Storage::disk('public')->url('media/01k9/teamfoto.webp');

    expect($response->json('strip.media'))->toBe([[
        'id' => $photo->id,
        'url' => $url,
        'thumb' => Storage::disk('public')->url('media/01k9/thumb-klein.webp'),
        'name' => 'teamfoto',
        'extension' => 'webp',
        'meta' => '1600 × 1067 · 412 kB',
        'folder' => 'Teams',
        'folder_id' => $folder->id,
        'alt' => 'Het eerste team',
        'image' => ['url' => $url, 'alt' => 'Het eerste team', 'width' => 1600, 'height' => 1067, 'focal' => ['x' => 0.5, 'y' => 0.5], 'variants' => [], 'srcset' => null],
        'croppable' => true,
        'kind' => 'image',
    ]])->and($response->getContent())->not->toContain('original-geheim123');
});

it('hands the side panel the document of a file field with its kind, and the kinds the field takes', function (): void {
    Storage::fake('public');
    app()->instance(StripTypes::class, new StripTypes([new Downloads]));
    $schema = Media::factory()->document()->withAlt('Wedstrijdschema najaar (pdf)')->create(['name' => 'wedstrijdschema', 'size' => 1_240_000]);
    $page = Page::factory()->withDraft([
        storedStrip('downloads', ['bestanden' => [['id' => '9b3e1a52-7c0d-4f8e-9a61-2d5c8e7f4b10', 'bestand' => $schema->id]]], id: 'a'),
    ])->create();

    $response = $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->getJson('made-cms/pages/'.$page->id.'/strips/a')
        ->assertOk()
        ->assertJsonPath('strip.fields.1.kinds', ['video'])
        ->assertJsonPath('strip.fields.2.fields.1.kinds', ['document']);

    expect($response->json('strip.media.0'))->toMatchArray([
        'id' => $schema->id,
        'thumb' => null,
        'name' => 'wedstrijdschema',
        'extension' => 'pdf',
        'meta' => '1,2 MB',
        'alt' => 'Wedstrijdschema najaar (pdf)',
        'image' => null,
        'croppable' => false,
        'kind' => 'document',
    ]);
});

it('sums up a file by its size, and an image without a size by its size on the disk only', function (array $attributes, string $meta): void {
    Storage::fake('public');
    $media = Media::factory()->create($attributes);
    $page = Page::factory()->withDraft([storedStrip('hero', ['foto' => $media->id], id: 'a')])->create();

    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->getJson('made-cms/pages/'.$page->id.'/strips/a')
        ->assertJsonPath('strip.media.0.meta', $meta);
})->with([
    'an svg' => [['mime' => 'image/svg+xml', 'width' => null, 'height' => null, 'size' => 2_300], '2 kB'],
    'a tiny file' => [['width' => 16, 'height' => 16, 'size' => 120], '16 × 16 · 1 kB'],
    'a large photo' => [['width' => 1920, 'height' => 1280, 'size' => 1_460_000], '1920 × 1280 · 1,5 MB'],
]);

it('names the type of a file by the extension of its address, in small letters', function (string $path, string $extension): void {
    Storage::fake('public');
    $media = Media::factory()->create(['path' => $path]);
    $page = Page::factory()->withDraft([storedStrip('hero', ['foto' => $media->id], id: 'a')])->create();

    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->getJson('made-cms/pages/'.$page->id.'/strips/a')
        ->assertJsonPath('strip.media.0.extension', $extension);
})->with([
    'a photo scaled down' => ['media/01k9/teamfoto.webp', 'webp'],
    'a drawing in capitals' => ['media/01k9/Logo.SVG', 'svg'],
    'a name with dots' => ['media/01k9/team.2026.gif', 'gif'],
]);

it('hands the side panel no files for a strip without images, and none for a file that is gone', function (): void {
    $page = Page::factory()->withDraft([
        storedStrip('hero', ['kop' => 'Zonder foto'], id: 'a'),
        storedStrip('hero', ['foto' => missingId()], id: 'b'),
    ])->create();
    $editor = Editor::factory()->create();

    $this->actingAs($editor, 'made-cms')->getJson('made-cms/pages/'.$page->id.'/strips/a')->assertJsonPath('strip.media', []);
    $this->actingAs($editor, 'made-cms')->getJson('made-cms/pages/'.$page->id.'/strips/b')
        ->assertJsonPath('strip.values.foto', missingId())
        ->assertJsonPath('strip.media', []);
});

it('offers every page of the site as a link, in the order of the menu', function (): void {
    $home = Page::factory()->published()->atPosition(0)->at('/')->create(['title' => 'Home']);
    $about = Page::factory()->atPosition(1)->at('/over-ons')->create(['title' => 'Over ons']);
    Page::factory()->under($about)->at('/over-ons/team')->create(['title' => 'Team']);
    $page = Page::factory()->atPosition(2)->withDraft([storedStrip('hero', id: 'a')])->create(['title' => 'Nieuw']);

    $pages = $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->getJson('made-cms/pages/'.$page->id.'/strips/a')
        ->json('pages');

    expect($pages)->toContain(['id' => $home->id, 'title' => 'Home', 'path' => '/', 'status' => 'published'])
        ->and(array_column($pages, 'title'))->toBe(['Home', 'Over ons', 'Team', 'Nieuw'])
        ->and($pages[1]['status'])->toBe('draft');
});

it('answers 404 for a strip that is not on the page', function (): void {
    $page = Page::factory()->withDraft([storedStrip('hero', id: 'a')])->create();

    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->getJson('made-cms/pages/'.$page->id.'/strips/b')
        ->assertNotFound();
});

it('answers 404 for a strip of a type that is no longer in the catalogue', function (): void {
    $page = Page::factory()->withDraft([storedStrip('oud', ['x' => 1], id: 'a')])->create();

    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->getJson('made-cms/pages/'.$page->id.'/strips/a')
        ->assertNotFound();
});

it('answers 404 for a page that does not exist', function (): void {
    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->getJson('made-cms/pages/'.missingId().'/strips/a')
        ->assertNotFound();
});

it('answers 401 to a guest', function (): void {
    $page = Page::factory()->withDraft([storedStrip('hero', id: 'a')])->create();

    $this->getJson('made-cms/pages/'.$page->id.'/strips/a')->assertUnauthorized();
});
