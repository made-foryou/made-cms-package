<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\SyncMediaUsage;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    Storage::fake('public');
    useStripTypes();
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('describes a file with the pages it is used on, each with its places and a link to its edit shell', function (): void {
    $media = Media::factory()->in(MediaFolder::factory()->create(['name' => 'Teams']))->create(['name' => 'teamfoto']);
    $teams = Page::factory()->withStrips([
        storedStrip('hero', ['foto' => $media->id], id: 'h'),
        storedStrip('cards', ['kaarten' => [['id' => 'k1', 'titel' => 'Een'], ['id' => 'k2', 'icoon' => $media->id]]], id: 'c'),
    ])->create(['title' => 'Onze teams']);
    $home = Page::factory()->withStrips([storedStrip('hero', ['foto' => $media->id], id: 'h')])->create(['title' => 'Home']);
    SyncMediaUsage::run($teams);
    SyncMediaUsage::run($home);

    $response = $this->getJson('made-cms/media/'.$media->id)
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($response->json('file.id'))->toBe($media->id)
        ->and($response->json('file.folder'))->toBe('Teams')
        ->and($response->json('heading'))->toBe('Gebruikt op 2 pagina’s')
        ->and($response->json('pages'))->toBe([
            [
                'title' => 'Home',
                'url' => '/made-cms/pages/'.$home->id.'/edit',
                'deleted' => false,
                'note' => null,
                'places' => [['where' => 'Hero › Foto', 'note' => null]],
            ],
            [
                'title' => 'Onze teams',
                'url' => '/made-cms/pages/'.$teams->id.'/edit',
                'deleted' => false,
                'note' => null,
                'places' => [
                    ['where' => 'Hero › Foto', 'note' => null],
                    ['where' => 'Kaarten › Item 2 › Icoon', 'note' => null],
                ],
            ],
        ])
        ->and((string) $response->getContent())->not->toContain($media->path);
});

it('says of a page that its places are only in the draft, or that the draft took them away', function (): void {
    $media = Media::factory()->create();
    $added = Page::factory()->published()->withStrips([])->withDraft([storedStrip('hero', ['foto' => $media->id], id: 'h')])->create(['title' => 'A']);
    $removed = Page::factory()->published()->withStrips([storedStrip('hero', ['foto' => $media->id], id: 'h')])->withDraft([])->create(['title' => 'B']);
    SyncMediaUsage::run($added);
    SyncMediaUsage::run($removed);

    $response = $this->getJson('made-cms/media/'.$media->id);

    expect($response->json('pages.*.note'))->toBe(['Alleen in het concept', 'Nog op de site, niet meer in het concept'])
        ->and($response->json('pages.*.places.0.note'))->toBe([null, null]);
});

it('says it of a place when the places of a page differ', function (): void {
    $media = Media::factory()->create();
    $hero = storedStrip('hero', ['foto' => $media->id], id: 'h');
    $page = Page::factory()->published()->withStrips([$hero])
        ->withDraft([$hero, storedStrip('cards', ['kaarten' => [['id' => 'k1', 'icoon' => $media->id]]], id: 'c')])
        ->create(['title' => 'Onze teams']);
    SyncMediaUsage::run($page);

    expect($this->getJson('made-cms/media/'.$media->id)->json('pages.0'))->toMatchArray([
        'note' => null,
        'places' => [
            ['where' => 'Hero › Foto', 'note' => null],
            ['where' => 'Kaarten › Item 1 › Icoon', 'note' => 'Alleen in het concept'],
        ],
    ]);
});

it('gives a deleted page without a link and leaves it out of the count', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->withStrips([storedStrip('hero', ['foto' => $media->id], id: 'h')])->create(['title' => 'Oud nieuws']);
    SyncMediaUsage::run($page);
    $page->delete();

    $response = $this->getJson('made-cms/media/'.$media->id)->assertOk();

    expect($response->json('heading'))->toBe('Gebruik')
        ->and($response->json('pages.0'))->toMatchArray(['title' => 'Oud nieuws', 'url' => null, 'deleted' => true, 'note' => 'In de prullenbak, telt niet mee'])
        ->and($response->json('pages.0.places.0.note'))->toBeNull();
});

it('says when a file is used nowhere', function (): void {
    $media = Media::factory()->document()->create();

    $this->getJson('made-cms/media/'.$media->id)
        ->assertOk()
        ->assertJsonPath('heading', 'Gebruik')
        ->assertJsonPath('pages', []);
});

it('answers 404 for a file that is not there', function (): void {
    $this->getJson('made-cms/media/404')->assertNotFound();
});

it('opens only for an editor who opens the media library', function (Role $role, bool $opens): void {
    $media = Media::factory()->create();

    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->getJson('made-cms/media/'.$media->id)
        ->assertStatus($opens ? 200 : 403);
})->with([
    'admin' => [Role::Admin, true],
    'webmaster' => [Role::Webmaster, true],
    'content manager' => [Role::ContentManager, true],
    'seo specialist' => [Role::SeoSpecialist, false],
    'owner' => [Role::Owner, false],
]);

it('answers 401 to a guest', function (): void {
    auth('made-cms')->logout();

    $this->getJson('made-cms/media/'.Media::factory()->create()->id)->assertUnauthorized();
});
