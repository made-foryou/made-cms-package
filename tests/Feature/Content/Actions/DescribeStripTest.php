<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Content\Actions\DescribeStrip;
use Made\CmsPackage\Content\Exceptions\StripNotFound;
use Made\CmsPackage\Content\Exceptions\UnknownStripType;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Tests\Fixtures\Strips\Banner;

beforeEach(function (): void {
    useStripTypes();
});

it('describes a strip with the fields of its type and its values', function (): void {
    $page = Page::factory()->withDraft([storedStrip('cards', ['kaarten' => [['id' => 'k1', 'titel' => 'Een']]], id: 'a')])->create();

    $strip = DescribeStrip::run($page, 'a');

    expect($strip->toArray())->toMatchArray([
        'id' => 'a',
        'type' => 'cards',
        'label' => 'Kaarten',
        'visible' => true,
        'values' => ['kaarten' => [['id' => 'k1', 'titel' => 'Een']]],
    ])->and(array_column($strip->fields, 'key'))->toBe(['kaarten'])
        ->and($strip->fields[0]['type'])->toBe('items');
});

it('describes the draft instead of the published strip', function (): void {
    $page = Page::factory()->published()
        ->withStrips([storedStrip('hero', ['kop' => 'Live'], id: 'a')])
        ->withDraft([storedStrip('hero', ['kop' => 'Concept'], visible: false, id: 'a')])
        ->create();

    $strip = DescribeStrip::run($page, 'a');

    expect($strip->values['kop'])->toBe('Concept')
        ->and($strip->visible)->toBeFalse();
});

it('describes the published strip of a page without a draft', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip('hero', ['kop' => 'Live'], id: 'a')])->create();

    expect(DescribeStrip::run($page, 'a')->values['kop'])->toBe('Live');
});

it('gives every field a value, a missing one its default, in the order of the type', function (): void {
    $page = Page::factory()->withDraft([storedStrip('hero', ['intro' => 'Hallo'], id: 'a')])->create();

    expect(DescribeStrip::run($page, 'a')->values)->toBe([
        'label' => null,
        'kop' => 'Welkom',
        'intro' => 'Hallo',
        'tekst' => null,
        'actie' => null,
        'knoppen' => [],
        'foto' => null,
    ]);
});

it('describes rich text cleaned, also when it reached the database without the package', function (): void {
    $page = Page::factory()->withDraft([
        storedStrip('hero', ['tekst' => '<p>Welkom</p><img src="x" onerror="alert(1)"><a href="javascript:alert(1)">klik</a>'], id: 'a'),
    ])->create();

    expect(DescribeStrip::run($page, 'a')->values['tekst'])->toBe('<p>Welkom</p><a>klik</a>');
});

it('leaves out stored values of fields the type no longer has', function (): void {
    $page = Page::factory()->withDraft([storedStrip('cards', ['kaarten' => [], 'oud' => 'weg'], id: 'a')])->create();

    expect(DescribeStrip::run($page, 'a')->values)->toBe(['kaarten' => []]);
});

it('refuses a strip that is not on the page', function (): void {
    $page = Page::factory()->withDraft([storedStrip('hero', id: 'a')])->create();

    expect(fn (): mixed => DescribeStrip::run($page, 'x'))->toThrow(StripNotFound::class);
});

it('refuses a strip of a type that is no longer in the catalogue', function (): void {
    $page = Page::factory()->withDraft([storedStrip('oud', ['x' => 1], id: 'a')])->create();

    expect(fn (): mixed => DescribeStrip::run($page, 'a'))->toThrow(UnknownStripType::class);
});

it('describes a heading stored as text as a text with its level', function (): void {
    app()->instance(StripTypes::class, new StripTypes([new Banner]));
    $page = Page::factory()->withDraft([storedStrip('banner', ['kop' => 'Welkom', 'kaarten' => [['id' => 'k1', 'titel' => 'Teams']]], id: 'a')])->create();

    expect(DescribeStrip::run($page, 'a')->values)->toBe([
        'kop' => ['text' => 'Welkom', 'tag' => 'h2'],
        'label' => null,
        'kaarten' => [['id' => 'k1', 'titel' => ['text' => 'Teams', 'tag' => 'h3']]],
    ]);
});

it('gives the files of the images of the strip, also in a list, for the side panel', function (): void {
    Storage::fake('public');
    $photo = Media::factory()->create();
    $icon = Media::factory()->create();
    $other = Media::factory()->create();
    $page = Page::factory()->withDraft([
        storedStrip('cards', ['kaarten' => [['id' => 'k1', 'titel' => 'Een', 'icoon' => $icon->id]]], id: 'a'),
        storedStrip('hero', ['foto' => (string) $photo->id], id: 'b'),
        storedStrip('hero', ['foto' => $other->id], id: 'c'),
    ])->create();

    expect(array_keys(DescribeStrip::run($page, 'a')->media->files))->toBe([$icon->id])
        ->and(array_keys(DescribeStrip::run($page, 'b')->media->files))->toBe([$photo->id])
        ->and(DescribeStrip::run($page, 'b')->values['foto'])->toBe($photo->id);
});

it('gives no files for a strip without images, without asking for the media disk', function (): void {
    config(['made-cms-package.media.disk' => 'nope']);
    $page = Page::factory()->withDraft([storedStrip('hero', ['kop' => 'Zonder foto'], id: 'a')])->create();

    expect(DescribeStrip::run($page, 'a')->media->files)->toBe([]);
});
