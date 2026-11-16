<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\ListMedia;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    Storage::fake('public');
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * @param  array<string, mixed>  $query
 * @return list<int>
 */
function listedMedia(array $query = []): array
{
    return array_column(test()->getJson('made-cms/media/files?'.http_build_query($query))->assertOk()->json('files'), 'id');
}

it('lists the files as the upload field gets one, newest first, with the count in words', function (): void {
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    $media = Media::factory()->in($folder)->withAlt('Het eerste team')->create([
        'name' => 'teamfoto',
        'path' => 'media/01k9/teamfoto.webp',
        'thumb_path' => 'media/01k9/thumb-klein.webp',
        'size' => 412_000,
        'width' => 1600,
        'height' => 1067,
        'created_at' => now(),
    ]);
    Media::factory()->create(['created_at' => now()->subDay()]);
    $url = Storage::disk('public')->url('media/01k9/teamfoto.webp');

    $response = $this->getJson('made-cms/media/files')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($response->json('files.0'))->toBe([
        'id' => $media->id,
        'url' => $url,
        'thumb' => Storage::disk('public')->url('media/01k9/thumb-klein.webp'),
        'name' => 'teamfoto',
        'extension' => 'webp',
        'meta' => '1600 × 1067 · 412 kB',
        'folder' => 'Teams',
        'folder_id' => $folder->id,
        'alt' => 'Het eerste team',
        'image' => [
            'url' => $url,
            'alt' => 'Het eerste team',
            'width' => 1600,
            'height' => 1067,
            'focal' => ['x' => 0.5, 'y' => 0.5],
        ],
        'croppable' => true,
        'kind' => 'image',
        'file' => 'teamfoto.webp',
        'size' => 412_000,
    ])
        ->and($response->json('page'))->toBe(1)
        ->and($response->json('total'))->toBe(2)
        ->and($response->json('more'))->toBeFalse()
        ->and($response->json('summary'))->toBe('2 bestanden')
        ->and((string) $response->getContent())->not->toContain('original');
});

it('counts the images in words when it lists images', function (int $count, string $summary): void {
    Media::factory()->count($count)->create();
    Media::factory()->document()->create();

    expect($this->getJson('made-cms/media/files?kind=image')->json('summary'))->toBe($summary);
})->with([[0, 'Geen afbeeldingen'], [1, '1 afbeelding'], [3, '3 afbeeldingen']]);

it('counts the images without an alt text in words when it lists those', function (int $count, string $summary): void {
    Media::factory()->count($count)->create();
    Media::factory()->withAlt('Het eerste team')->create();

    expect($this->getJson('made-cms/media/files?without_alt=1')->json('summary'))->toBe($summary);
})->with([[0, 'Geen afbeeldingen zonder alt-tekst'], [1, '1 afbeelding zonder alt-tekst'], [2, '2 afbeeldingen zonder alt-tekst']]);

it('lists the files of a folder, of a kind, without an alt text or by name', function (): void {
    $teams = MediaFolder::factory()->create();
    $veld = Media::factory()->in($teams)->create(['name' => 'veld']);
    $clubavond = Media::factory()->in($teams)->withAlt('De clubavond')->create(['name' => 'clubavond']);
    $schema = Media::factory()->in($teams)->document()->create(['name' => 'schema']);
    Media::factory()->create(['name' => 'elders']);

    expect(listedMedia(['folder' => $teams->id, 'sort' => 'name']))->toBe([$clubavond->id, $schema->id, $veld->id])
        ->and(listedMedia(['folder' => $teams->id, 'kind' => 'document']))->toBe([$schema->id])
        ->and(listedMedia(['folder' => $teams->id, 'without_alt' => 1]))->toBe([$veld->id]);
});

it('names the search in the count in words', function (): void {
    Media::factory()->create(['name' => 'teamfoto']);
    Media::factory()->document()->create(['name' => 'teamschema']);

    expect($this->getJson('made-cms/media/files?q=+team+')->json('summary'))->toBe('2 bestanden voor “team”')
        ->and($this->getJson('made-cms/media/files?q=kerstdiner&kind=image')->json('summary'))->toBe('Geen afbeeldingen voor “kerstdiner”');
});

it('finds files by their alt text', function (): void {
    $byAlt = Media::factory()->withAlt('Het Merwestad-team op het veld')->create(['name' => 'veld']);
    Media::factory()->create(['name' => 'clubavond']);

    expect(listedMedia(['q' => 'merwestad']))->toBe([$byAlt->id]);
});

it('finds files by the title of a page they are used on', function (): void {
    $used = Media::factory()->create(['name' => 'teamfoto']);
    MediaUsage::factory()->for($used)->for(Page::factory()->state(['title' => 'Onze teams']))->create();
    Media::factory()->create(['name' => 'clubavond']);

    expect(listedMedia(['q' => 'onze teams']))->toBe([$used->id]);
});

it('gives the files a page at a time', function (): void {
    Media::factory()->count(ListMedia::PER_PAGE + 1)->create();

    $this->getJson('made-cms/media/files')->assertJsonCount(ListMedia::PER_PAGE, 'files')->assertJsonPath('more', true);
    $this->getJson('made-cms/media/files?page=2')
        ->assertJsonCount(1, 'files')
        ->assertJsonPath('page', 2)
        ->assertJsonPath('total', ListMedia::PER_PAGE + 1)
        ->assertJsonPath('more', false);
});

it('refuses a folder that is gone', function (): void {
    $this->getJson('made-cms/media/files?folder=404')
        ->assertUnprocessable()
        ->assertJsonPath('errors.folder.0', 'Deze map bestaat niet meer. Herlaad de pagina en kies een andere map.');
});

it('refuses a filter it does not know', function (string $query, string $field): void {
    $this->getJson('made-cms/media/files?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'a kind' => ['kind=foto', 'kind'],
    'an order' => ['sort=oldest', 'sort'],
    'a page' => ['page=0', 'page'],
    'a folder' => ['folder=teams', 'folder'],
    'without alt' => ['without_alt=misschien', 'without_alt'],
]);

it('opens for every editor who opens the pages or the media library', function (Role $role): void {
    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->getJson('made-cms/media/files')
        ->assertOk();
})->with([Role::Owner, Role::SeoSpecialist, Role::ContentManager, Role::Admin]);

it('forbids it to an editor who opens neither', function (): void {
    $this->actingAs(Editor::factory()->withRoles()->create(), 'made-cms')
        ->getJson('made-cms/media/files')
        ->assertForbidden();
});

it('answers 401 to a guest', function (): void {
    auth('made-cms')->logout();

    $this->getJson('made-cms/media/files')->assertUnauthorized();
});

it('leaves the address made-cms/media to the screen of the media library', function (): void {
    $this->get('made-cms/media')->assertOk()->assertSee('data-media-library', false)->assertDontSee('wordt nog gebouwd');
});
