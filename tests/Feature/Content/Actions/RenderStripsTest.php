<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Content\Actions\RenderStrips;
use Made\CmsPackage\Content\Data\EditMode;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Tests\Fixtures\Strips\Banner;
use Made\CmsPackage\Tests\Fixtures\Strips\Teaser;

beforeEach(function (): void {
    useStripTypes();
});

it('renders the visible strips of a page in order as their id, type and rendered fields', function (): void {
    $page = Page::factory()->published()->withStrips([
        storedStrip('cards', ['kaarten' => [['titel' => 'Een']]], id: 'a'),
        storedStrip('hero', ['kop' => 'TTV Merwestad', 'knoppen' => [['tekst' => 'Lid worden', 'gaat_naar' => ['url' => '/lid-worden']]]], id: 'b'),
    ])->create();

    expect(RenderStrips::run($page)->toArray())->toBe([
        ['id' => 'a', 'type' => 'cards', 'kaarten' => [['id' => null, 'titel' => 'Een', 'icoon' => null]], 'aantal' => 1],
        [
            'id' => 'b',
            'type' => 'hero',
            'label' => null,
            'kop' => 'TTV Merwestad',
            'intro' => null,
            'tekst' => null,
            'actie' => null,
            'knoppen' => [['id' => null, 'tekst' => 'Lid worden', 'gaat_naar' => '/lid-worden', 'stijl' => null]],
            'foto' => null,
        ],
    ]);
});

it('renders every item of a list with its id', function (): void {
    $page = Page::factory()->published()->withStrips([
        storedStrip('cards', ['kaarten' => [['id' => '6f1d2c3b-4a5e-4f60-8b7c-1d2e3f4a5b6c', 'titel' => 'Een']]], id: 'a'),
    ])->create();

    expect(RenderStrips::run($page)->toArray()[0]['kaarten'] ?? null)
        ->toBe([['id' => '6f1d2c3b-4a5e-4f60-8b7c-1d2e3f4a5b6c', 'titel' => 'Een', 'icoon' => null]]);
});

it('leaves out hidden strips', function (): void {
    $page = Page::factory()->published()->withStrips([
        storedStrip('cards', id: 'a', visible: false),
        storedStrip('cards', id: 'b'),
    ])->create();

    expect(array_column(RenderStrips::run($page)->toArray(), 'id'))->toBe(['b']);
});

it('leaves out strips of a type that is no longer in the catalogue', function (): void {
    $page = Page::factory()->published()->withStrips([
        storedStrip('oud', ['x' => 1], id: 'a'),
        storedStrip('cards', id: 'b'),
    ])->create();

    expect(array_column(RenderStrips::run($page)->toArray(), 'id'))->toBe(['b']);
});

it('renders a page without strips as nothing', function (): void {
    expect(RenderStrips::run(Page::factory()->published()->create())->isEmpty())->toBeTrue();
});

it('renders a link to a page as its current path, and as null once the page is a draft', function (): void {
    $target = Page::factory()->published()->at('/over-ons')->create();
    $page = Page::factory()->published()->withStrips([
        storedStrip('hero', ['knoppen' => [['tekst' => 'Over ons', 'gaat_naar' => ['page' => $target->id]]]], id: 'a'),
    ])->create();

    expect(RenderStrips::run($page)->strips[0]->knoppen[0]['gaat_naar'])->toBe('/over-ons');

    $target->route?->update(['path' => '/wie-we-zijn']);

    expect(RenderStrips::run($page)->strips[0]->knoppen[0]['gaat_naar'])->toBe('/wie-we-zijn');

    $target->update(['status' => 'draft']);

    expect(RenderStrips::run($page)->strips[0]->knoppen[0]['gaat_naar'])->toBeNull();
});

it('renders the published strips, not the draft', function (): void {
    $page = Page::factory()->published()
        ->withStrips([storedStrip('hero', ['kop' => 'Live'], id: 'a')])
        ->withDraft([storedStrip('hero', ['kop' => 'Concept'], id: 'a')])
        ->create();

    expect(RenderStrips::run($page)->strips[0]->kop)->toBe('Live');
});

it('renders the draft instead of the published strips in the edit mode', function (): void {
    app()->instance(EditMode::class, EditMode::on());
    $page = Page::factory()->published()
        ->withStrips([storedStrip('hero', ['kop' => 'Live'], id: 'a')])
        ->withDraft([storedStrip('hero', ['kop' => 'Concept'], id: 'a'), storedStrip('cards', id: 'b')])
        ->create();

    expect(RenderStrips::run($page)->strips[0]->kop)->toBe('Concept')
        ->and(array_column(RenderStrips::run($page)->toArray(), 'id'))->toBe(['a', 'b']);
});

it('renders the published strips in the edit mode when the page has no draft', function (): void {
    app()->instance(EditMode::class, EditMode::on());
    $page = Page::factory()->published()->withStrips([storedStrip('hero', ['kop' => 'Live'], id: 'a')])->create();

    expect(RenderStrips::run($page)->strips[0]->kop)->toBe('Live');
});

it('renders the draft of a page that was never published in the edit mode', function (): void {
    app()->instance(EditMode::class, EditMode::on());
    $page = Page::factory()->withDraft([storedStrip('hero', ['kop' => 'Nieuw'], id: 'a')])->create();

    expect(RenderStrips::run($page)->strips[0]->kop)->toBe('Nieuw');
});

it('leaves hidden strips and unknown types out of the draft in the edit mode', function (): void {
    app()->instance(EditMode::class, EditMode::on());
    $page = Page::factory()->withDraft([
        storedStrip('cards', id: 'a', visible: false),
        storedStrip('oud', ['x' => 1], id: 'b'),
        storedStrip('cards', id: 'c'),
    ])->create();

    expect(array_column(RenderStrips::run($page)->toArray(), 'id'))->toBe(['c']);
});

it('renders no script that reached the database without the package', function (): void {
    $page = Page::factory()->published()->withStrips([
        storedStrip('hero', ['tekst' => '<p>Welkom</p><script>alert(1)</script><img src="x" onerror="alert(1)">'], id: 'a'),
    ])->create();

    expect(RenderStrips::run($page)->strips[0]->tekst)->toBe('<p>Welkom</p>');
});

it('renders no script that reached the draft without the package in the edit mode', function (): void {
    app()->instance(EditMode::class, EditMode::on());
    $page = Page::factory()->published()
        ->withStrips([storedStrip('hero', ['tekst' => '<p>Live</p>'], id: 'a')])
        ->withDraft([storedStrip('hero', ['tekst' => '<p>Concept</p><script>alert(1)</script><a href="javascript:alert(1)">klik</a>'], id: 'a')])
        ->create();

    expect(RenderStrips::run($page)->strips[0]->tekst)->toBe('<p>Concept</p><a>klik</a>');
});

it('keeps the id and type of a strip when the extra data of the site uses those names', function (): void {
    app()->instance(StripTypes::class, new StripTypes([new Teaser]));
    $page = Page::factory()->published()->withStrips([
        storedStrip('teaser', ['kop' => 'Van de redacteur', 'lees_meer' => ['url' => '/nieuws']], id: 'a'),
    ])->create();

    expect(RenderStrips::run($page)->toArray())->toBe([
        ['id' => 'a', 'type' => 'teaser', 'kop' => 'Uit with', 'lees_meer' => '/nieuws', 'doel' => '/nieuws'],
    ]);
});

it('renders a heading as its text and level, also one stored as text', function (): void {
    app()->instance(StripTypes::class, new StripTypes([new Banner]));
    $page = Page::factory()->published()->withStrips([
        storedStrip('banner', ['kop' => ['text' => 'Welkom', 'tag' => 'h1'], 'kaarten' => [['id' => 'k1', 'titel' => 'Teams']]], id: 'a'),
        storedStrip('banner', ['kop' => 'Agenda'], id: 'b'),
    ])->create();

    expect(RenderStrips::run($page)->toArray())->toBe([
        ['id' => 'a', 'type' => 'banner', 'kop' => ['text' => 'Welkom', 'tag' => 'h1'], 'label' => null, 'kaarten' => [['id' => 'k1', 'titel' => ['text' => 'Teams', 'tag' => 'h3']]]],
        ['id' => 'b', 'type' => 'banner', 'kop' => ['text' => 'Agenda', 'tag' => 'h2'], 'label' => null, 'kaarten' => []],
    ]);
});

it('renders an image as the url, alt text, size and focal point of its file', function (): void {
    Storage::fake('public');
    $media = Media::factory()->withAlt('Het eerste team')->create(['path' => 'media/01k9/teamfoto.webp', 'width' => 1600, 'height' => 1067, 'focal_x' => 0.3, 'focal_y' => 0.2]);
    $page = Page::factory()->published()->withStrips([storedStrip('hero', ['foto' => $media->id], id: 'a')])->create();

    expect(RenderStrips::run($page)->strips[0]->foto)->toBe([
        'url' => Storage::disk('public')->url('media/01k9/teamfoto.webp'),
        'alt' => 'Het eerste team',
        'width' => 1600,
        'height' => 1067,
        'focal' => ['x' => 0.3, 'y' => 0.2],
    ]);
});

it('renders an image in an item of a list', function (): void {
    Storage::fake('public');
    $icon = Media::factory()->create(['path' => 'media/01k9/bal.webp']);
    $page = Page::factory()->published()->withStrips([
        storedStrip('cards', ['kaarten' => [['id' => 'k1', 'titel' => 'Trainen', 'icoon' => $icon->id], ['id' => 'k2', 'titel' => 'Spelen']]], id: 'a'),
    ])->create();

    $cards = RenderStrips::run($page)->strips[0]->kaarten;

    expect($cards[0]['icoon']['url'] ?? null)->toBe(Storage::disk('public')->url('media/01k9/bal.webp'))
        ->and($cards[1]['icoon'])->toBeNull();
});

it('renders an image whose file is gone or is no image as null, so the site shows its own fallback', function (Closure $media): void {
    Storage::fake('public');
    $page = Page::factory()->published()->withStrips([storedStrip('hero', ['foto' => $media()], id: 'a')])->create();

    expect(RenderStrips::run($page)->strips[0]->foto)->toBeNull();
})->with([
    'a file that is gone' => [fn (): int => 999],
    'a file that was deleted' => [function (): int {
        $media = Media::factory()->create();
        $media->delete();

        return $media->id;
    }],
    'a document' => [fn (): int => Media::factory()->document()->create()->id],
]);

it('renders an svg without a size', function (): void {
    Storage::fake('public');
    $logo = Media::factory()->create(['mime' => 'image/svg+xml', 'path' => 'media/01k9/logo.svg', 'original_path' => null, 'width' => null, 'height' => null]);
    $page = Page::factory()->published()->withStrips([storedStrip('hero', ['foto' => $logo->id], id: 'a')])->create();

    expect(RenderStrips::run($page)->strips[0]->foto)->toMatchArray(['width' => null, 'height' => null, 'alt' => '']);
});

it('finds the images of every strip of a page with one query for the files', function (): void {
    Storage::fake('public');
    $media = Media::factory()->count(3)->create();
    $page = Page::factory()->published()->withStrips([
        storedStrip('hero', ['foto' => $media[0]->id], id: 'a'),
        storedStrip('hero', ['foto' => $media[1]->id], id: 'b'),
        storedStrip('cards', ['kaarten' => [['titel' => 'Een', 'icoon' => $media[2]->id], ['titel' => 'Twee', 'icoon' => $media[0]->id]]], id: 'c'),
    ])->create();

    DB::enableQueryLog();
    $strips = RenderStrips::run($page);
    $queries = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'cms_media"'));

    expect($queries)->toHaveCount(1)
        ->and($strips->strips[1]->foto)->not->toBeNull()
        ->and($strips->strips[2]->kaarten[1]['icoon'])->toBe($strips->strips[0]->foto);
});

it('never asks for the media disk for a page without images', function (): void {
    config(['made-cms-package.media.disk' => 'nope']);
    $page = Page::factory()->published()->withStrips([storedStrip('hero', ['kop' => 'Zonder foto'], id: 'a')])->create();

    expect(RenderStrips::run($page)->strips[0]->foto)->toBeNull();
});

it('leaves the files of hidden strips and unknown types alone', function (): void {
    config(['made-cms-package.media.disk' => 'nope']);
    $media = Media::factory()->create();
    $page = Page::factory()->published()->withStrips([
        storedStrip('hero', ['foto' => $media->id], visible: false, id: 'a'),
        storedStrip('oud', ['foto' => $media->id], id: 'b'),
    ])->create();

    expect(RenderStrips::run($page)->isEmpty())->toBeTrue();
});

it('hands the site the url of an image, never its place on the disk or its original', function (): void {
    Storage::fake('public');
    $media = Media::factory()->create(['path' => 'media/01k9/teamfoto.webp', 'original_path' => 'media/01k9/original-geheim123.jpg']);
    $page = Page::factory()->published()->withStrips([storedStrip('hero', ['foto' => $media->id], id: 'a')])->create();

    $json = (string) json_encode(RenderStrips::run($page)->toArray());

    expect($json)->not->toContain('original-geheim123')
        ->and($json)->not->toContain('"path"')
        ->and($json)->not->toContain('"original_path"')
        ->and(array_keys(RenderStrips::run($page)->toArray()[0]['foto'] ?? []))->toBe(['url', 'alt', 'width', 'height', 'focal']);
});
