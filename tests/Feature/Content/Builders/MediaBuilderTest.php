<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Builders\MediaBuilder;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;

it('uses the custom builder for media queries', function (): void {
    expect(Media::query())->toBeInstanceOf(MediaBuilder::class);
});

it('only returns images', function (): void {
    $image = Media::factory()->create();
    Media::factory()->document()->create();
    Media::factory()->video()->create();

    expect(Media::query()->images()->pluck('id')->all())->toBe([$image->id]);
});

it('only returns the files in a folder', function (): void {
    $teams = MediaFolder::factory()->create();
    $inTeams = Media::factory()->in($teams)->create();
    Media::factory()->in(MediaFolder::factory()->create())->create();
    Media::factory()->create();

    expect(Media::query()->inFolder($teams)->pluck('id')->all())->toBe([$inTeams->id]);
});

it('returns the images without an alt text, never a document', function (): void {
    $without = Media::factory()->create();
    Media::factory()->withAlt('Het eerste team na de kampioenswedstrijd')->create();
    Media::factory()->document()->create();

    expect(Media::query()->withoutAlt()->pluck('id')->all())->toBe([$without->id]);
});

it('finds files by their name or alt text, whatever the capitals', function (): void {
    $byName = Media::factory()->create(['name' => 'teamfoto-merwestad-4']);
    $byAlt = Media::factory()->withAlt('Het Merwestad-team op het veld')->create(['name' => 'veld']);
    Media::factory()->create(['name' => 'clubavond-oktober']);

    expect(Media::query()->search('MERWESTAD')->orderBy('id')->pluck('id')->all())->toBe([$byName->id, $byAlt->id]);
});

it('takes wildcards in a search literally', function (string $term): void {
    Media::factory()->create(['name' => 'teamfoto']);
    $literal = Media::factory()->create(['name' => 'korting-50%_najaar!']);

    expect(Media::query()->search($term)->pluck('id')->all())->toBe([$literal->id]);
})->with(['percent' => ['50%'], 'underscore' => ['%_n'], 'escape character' => ['!']]);

it('finds every file for a blank search', function (): void {
    Media::factory()->count(2)->create();

    expect(Media::query()->search('  ')->count())->toBe(2);
});

it('returns the files in use somewhere, in a draft, a published version or on a deleted page', function (): void {
    $published = MediaUsage::factory()->create()->media_id;
    $draft = MediaUsage::factory()->draft()->create()->media_id;
    $deleted = MediaUsage::factory()->for(Page::factory()->deletedAt(now()))->create()->media_id;
    Media::factory()->create();

    expect(Media::query()->inUse()->orderBy('id')->pluck('id')->all())->toBe([$published, $draft, $deleted]);
});

it('counts a file in use once, whatever the number of places', function (): void {
    $media = Media::factory()->create();
    MediaUsage::factory()->for($media)->count(3)->create();

    expect(Media::query()->inUse()->count())->toBe(1);
});

it('counts the images in use without an alt text', function (): void {
    $usedWithout = Media::factory()->create();
    MediaUsage::factory()->for($usedWithout)->create();
    MediaUsage::factory()->for(Media::factory()->withAlt('Het eerste team'))->create();
    Media::factory()->create();

    expect(Media::query()->withoutAlt()->inUse()->pluck('id')->all())->toBe([$usedWithout->id]);
});

it('loads the places of the files with their pages, deleted pages too', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->create(['title' => 'Onze teams']);
    $gone = Page::factory()->deletedAt(now())->create(['title' => 'Oud nieuws']);
    MediaUsage::factory()->for($media)->for($page)->create();
    MediaUsage::factory()->for($media)->for($gone)->create();

    $loaded = Media::query()->withUsage()->findOrFail($media->id);

    expect($loaded->relationLoaded('usages'))->toBeTrue()
        ->and($loaded->usages->map(static fn (MediaUsage $usage): ?string => $usage->page?->title)->sort()->values()->all())
        ->toBe(['Onze teams', 'Oud nieuws']);
});

it('only returns the files of a kind', function (MediaKind $kind): void {
    $files = [
        MediaKind::Image->value => Media::factory()->create()->id,
        MediaKind::Document->value => Media::factory()->document()->create()->id,
        MediaKind::Video->value => Media::factory()->video()->create()->id,
    ];

    expect(Media::query()->ofKind($kind)->pluck('id')->all())->toBe([$files[$kind->value]]);
})->with(MediaKind::cases());

it('puts the newest files first, the last one stored first within a second', function (): void {
    $old = Media::factory()->create(['created_at' => now()->subDay()]);
    $first = Media::factory()->create(['created_at' => now()]);
    $second = Media::factory()->create(['created_at' => now()]);

    expect(Media::query()->newestFirst()->pluck('id')->all())->toBe([$second->id, $first->id, $old->id]);
});

it('orders the files by their name, the first one stored first for the same name', function (): void {
    $veld = Media::factory()->create(['name' => 'veld']);
    $teamfoto = Media::factory()->create(['name' => 'teamfoto']);
    $clubavond = Media::factory()->create(['name' => 'clubavond']);
    $tweede = Media::factory()->create(['name' => 'teamfoto']);

    expect(Media::query()->byName()->pluck('id')->all())->toBe([$clubavond->id, $teamfoto->id, $tweede->id, $veld->id]);
});

it('counts the files per folder by its id, never the files without a folder', function (): void {
    $teams = MediaFolder::factory()->create();
    $nieuws = MediaFolder::factory()->create();
    Media::factory()->in($teams)->count(2)->create();
    Media::factory()->in($nieuws)->document()->create();
    Media::factory()->create();

    expect(Media::query()->countPerFolder())->toBe([$teams->id => 2, $nieuws->id => 1])
        ->and(Media::query()->images()->countPerFolder())->toBe([$teams->id => 2]);
});

it('counts per folder whatever order the query has', function (): void {
    $teams = MediaFolder::factory()->create();
    Media::factory()->in($teams)->count(2)->create();

    expect(Media::query()->newestFirst()->countPerFolder())->toBe([$teams->id => 2]);
});

it('counts nothing in an empty library', function (): void {
    expect(Media::query()->countPerFolder())->toBe([]);
});
