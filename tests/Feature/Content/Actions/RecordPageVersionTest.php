<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\RecordPageVersion;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\PageVersion;

beforeEach(function (): void {
    useStripTypes();
});

/**
 * Records the working copy of the page as the version that goes live now.
 */
function recordVersion(Page $page, ?int $editorId = null): PageVersion
{
    return RecordPageVersion::run(
        $page,
        PageStrips::fromArray($page->draft_strips ?? $page->strips),
        CarbonImmutable::parse('2026-10-20 14:12:00'),
        $editorId,
    );
}

it('records the first publication of a page as version 1', function (): void {
    $editor = Editor::factory()->create();
    $draft = [storedStrip('hero', ['kop' => 'Welkom'], id: 'hero'), storedStrip('cards', id: 'cards')];
    $page = Page::factory()->withDraft($draft)->create();

    $version = recordVersion($page, $editor->id);

    $stored = PageVersion::query()->findOrFail($version->id);

    expect($stored->page_id)->toBe($page->id)
        ->and($stored->number)->toBe(1)
        ->and($stored->strips)->toBeStoredAs($draft)
        ->and($stored->changes)->toBeStoredAs(['first' => true, 'added' => [], 'changed' => [], 'moved' => [], 'removed' => []])
        ->and($stored->published_by)->toBe($editor->id)
        ->and($stored->published_at->toDateTimeString())->toBe('2026-10-20 14:12:00');
});

it('records what the draft of a published page changes', function (): void {
    $published = [
        storedStrip('hero', ['kop' => 'Welkom'], id: 'a'),
        storedStrip('cards', id: 'b'),
        storedStrip('hero', ['kop' => 'Lid worden'], id: 'c'),
        storedStrip('cards', id: 'd'),
    ];
    $draft = [
        storedStrip('hero', ['kop' => 'Lid worden'], id: 'c'),
        storedStrip('hero', ['kop' => 'Welkom terug'], id: 'a'),
        storedStrip('cards', id: 'b'),
        storedStrip('cards', id: 'e'),
    ];
    $page = Page::factory()->published()->withStrips($published)->withDraft($draft)->create();

    $version = recordVersion($page);

    expect($version->changes)->toBeStoredAs([
        'first' => false,
        'added' => ['e'],
        'changed' => ['a'],
        'moved' => ['c'],
        'removed' => [storedStrip('cards', id: 'd')],
    ]);
});

it('counts on from the highest number of the page', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip('cards', id: 'a')])->withDraft([])->create();
    PageVersion::factory()->for($page)->number(1)->create();
    PageVersion::factory()->for($page)->number(4)->create();
    PageVersion::factory()->for(Page::factory())->number(9)->create();

    expect(recordVersion($page)->number)->toBe(5);
});

it('does not know who published without an editor', function (): void {
    $page = Page::factory()->withDraft([])->create();

    expect(recordVersion($page)->published_by)->toBeNull();
});
