<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\ListPageVersions;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Data\VersionChanges;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\PageVersion;

beforeEach(function (): void {
    useStripTypes();
});

it('lists the versions of a page, the newest first', function (): void {
    $editor = Editor::factory()->create();
    $page = Page::factory()->published()->create();
    PageVersion::factory()->for($page)->number(1)->create(['published_at' => CarbonImmutable::parse('2024-03-12 10:00:00')]);
    PageVersion::factory()->for($page)->number(2)->publishedBy($editor)->create(['published_at' => CarbonImmutable::parse('2026-10-20 14:12:00')]);
    PageVersion::factory()->for(Page::factory())->number(3)->create();

    $versions = ListPageVersions::run($page)->versions;

    expect(array_map(fn ($version): int => $version->number, $versions))->toBe([2, 1])
        ->and($versions[0]->publishedAt->toDateTimeString())->toBe('2026-10-20 14:12:00')
        ->and($versions[0]->publishedBy)->toBe($editor->id)
        ->and($versions[1]->publishedBy)->toBeNull()
        ->and($versions[1]->first)->toBeTrue();
});

it('says only the newest version of a published page is live', function (): void {
    $published = Page::factory()->published()->create();
    PageVersion::factory()->for($published)->count(2)->sequence(['number' => 1], ['number' => 2])->create();
    $draft = Page::factory()->create();
    PageVersion::factory()->for($draft)->create();

    expect(array_map(fn ($version): bool => $version->live, ListPageVersions::run($published)->versions))->toBe([true, false])
        ->and(ListPageVersions::run($draft)->versions[0]->live)->toBeFalse();
});

it('names the strips a version changed as the structure names them', function (): void {
    $page = Page::factory()->published()->create();
    PageVersion::factory()->for($page)->withStrips([
        storedStrip('hero', ['kop' => 'Lid worden'], id: 'hero'),
        storedStrip('cards', ['kaarten' => [['id' => 'k1', 'titel' => 'Teams'], ['id' => 'k2', 'titel' => 'Competitie']]], id: 'cards'),
        storedStrip('cards', ['kaarten' => []], id: 'empty'),
        storedStrip('quote', ['tekst' => 'Weg uit de catalogus'], id: 'quote'),
    ])->create(['changes' => (new VersionChanges(
        first: false,
        added: ['hero', 'gone'],
        changed: ['cards', 'empty'],
        moved: ['quote'],
        removed: [new Strip('old', 'hero', true, ['kop' => 'Oude kop']), new Strip('older', 'banner', true, [])],
    ))->toArray()]);

    $version = ListPageVersions::run($page)->versions[0];

    expect($version->first)->toBeFalse()
        ->and($version->added)->toBe(['Lid worden'])
        ->and($version->changed)->toBe(['Teams, Competitie', 'Kaarten'])
        ->and($version->moved)->toBe(['quote'])
        ->and($version->removed)->toBe(['Oude kop', 'banner']);
});

it('says whether the page has a draft now', function (): void {
    $withDraft = Page::factory()->published()->withStrips([])->withDraft([storedStrip('cards')])->create();
    $without = Page::factory()->published()->create();

    expect(ListPageVersions::run($withDraft)->draft)->toBeTrue()
        ->and(ListPageVersions::run($without)->draft)->toBeFalse()
        ->and(ListPageVersions::run($without)->versions)->toBe([]);
});

it('gives the editors who published, each once', function (): void {
    $editor = Editor::factory()->create();
    $page = Page::factory()->published()->create();
    PageVersion::factory()->for($page)->count(3)->sequence(
        ['number' => 1, 'published_by' => $editor->id],
        ['number' => 2, 'published_by' => null],
        ['number' => 3, 'published_by' => $editor->id],
    )->create();

    expect(ListPageVersions::run($page)->editors())->toBe([$editor->id]);
});
