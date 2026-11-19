<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Content\Actions\AddStrip;
use Made\CmsPackage\Content\Actions\ChangeDraft;
use Made\CmsPackage\Content\Actions\DiscardDraft;
use Made\CmsPackage\Content\Actions\MoveStrip;
use Made\CmsPackage\Content\Actions\PublishPage;
use Made\CmsPackage\Content\Actions\RemoveStrip;
use Made\CmsPackage\Content\Actions\RestoreStrip;
use Made\CmsPackage\Content\Actions\SetStripVisibility;
use Made\CmsPackage\Content\Actions\UpdateStrip;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Events\DraftDiscarded;
use Made\CmsPackage\Content\Events\StripRemoved;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
});

/**
 * The ids of the strips in their order.
 *
 * @return list<string>
 */
function draftStripIds(PageStrips $strips): array
{
    return array_map(static fn (Strip $strip): string => $strip->id, $strips->strips);
}

/**
 * The working copy of the page as it is stored now.
 */
function storedDraft(Page $page): PageStrips
{
    $stored = Page::query()->findOrFail($page->id);

    return PageStrips::fromArray($stored->draft_strips ?? $stored->strips);
}

/**
 * The title of the first card of a cards strip in the stored draft.
 */
function firstCardTitle(Page $page, string $stripId): mixed
{
    /** @var list<array<string, mixed>> $cards */
    $cards = storedDraft($page)->find($stripId)?->values['kaarten'] ?? [];

    return $cards[0]['titel'] ?? null;
}

it('hands the change the working copy as it is stored, not as the loaded page holds it', function (): void {
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->create();
    $loaded = Page::query()->findOrFail($page->id);
    $page->draft_strips = [storedStrip(id: 'a'), storedStrip(id: 'b')];
    $page->save();

    $ids = ChangeDraft::run($loaded, static fn (PageStrips $strips): array => draftStripIds($strips));

    expect($ids)->toBe(['a', 'b']);
});

it('hands the change the published strips when the page has no draft', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip(id: 'a')])->create();

    $ids = ChangeDraft::run($page, static fn (PageStrips $strips): array => draftStripIds($strips));

    expect($ids)->toBe(['a']);
});

it('stores what the change writes on the page and gives back what the change returns', function (): void {
    $page = Page::factory()->withDraft([storedStrip(id: 'a'), storedStrip(id: 'b')])->create();

    $result = ChangeDraft::run($page, static function (PageStrips $strips, Page $page): string {
        $page->draft_strips = $strips->remove('a')->toArray();

        return 'done';
    });

    expect($result)->toBe('done')
        ->and(draftStripIds(storedDraft($page)))->toBe(['b']);
});

it('leaves the page it gets with the stored state', function (): void {
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->create();
    $loaded = Page::query()->findOrFail($page->id);
    $page->draft_strips = [storedStrip(id: 'a'), storedStrip(id: 'b')];
    $page->save();

    ChangeDraft::run($loaded, static function (PageStrips $strips, Page $page): void {
        $page->draft_strips = $strips->remove('a')->toArray();
    });

    expect(draftStripIds(PageStrips::fromArray($loaded->draft_strips ?? [])))->toBe(['b'])
        ->and($loaded->isDirty())->toBeFalse();
});

it('stores nothing when the change throws', function (): void {
    $draft = [storedStrip(id: 'a')];
    $page = Page::factory()->withDraft($draft)->create();

    expect(fn (): mixed => ChangeDraft::run($page, static function (PageStrips $strips, Page $page): never {
        $page->draft_strips = [];
        $page->save();

        throw new RuntimeException('Refused.');
    }))->toThrow(RuntimeException::class, 'Refused.');

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBeStoredAs($draft);
});

it('reads the page inside its transaction', function (): void {
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->create();
    $outside = DB::transactionLevel();
    $levels = [];

    DB::listen(static function (QueryExecuted $query) use (&$levels): void {
        if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'cms_pages')) {
            $levels[] = DB::transactionLevel();
        }
    });

    ChangeDraft::run($page, static fn (PageStrips $strips): int => $strips->count());

    expect($levels)->toBe([$outside + 1]);
});

it('refuses a page that was deleted after it was loaded', function (): void {
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->create();
    $loaded = Page::query()->findOrFail($page->id);
    $page->delete();

    expect(fn (): mixed => ChangeDraft::run($loaded, static fn (PageStrips $strips): int => $strips->count()))
        ->toThrow(ModelNotFoundException::class);
});

it('dispatches the events of the change after the commit', function (): void {
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->create();
    $outside = DB::transactionLevel();
    $dispatched = [];

    Event::listen(StripRemoved::class, static function () use (&$dispatched): void {
        $dispatched[] = DB::transactionLevel();
    });

    ChangeDraft::run($page, static function (PageStrips $strips, Page $page) use (&$dispatched): void {
        $page->draft_strips = $strips->remove('a')->toArray();
        StripRemoved::dispatch($page, $strips->strips[0]);

        expect($dispatched)->toBe([]);
    });

    expect($dispatched)->toBe([$outside]);
});

it('waits for the commit of a transaction of the host around it', function (): void {
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->create();
    $dispatched = 0;

    Event::listen(DraftDiscarded::class, static function () use (&$dispatched): void {
        $dispatched++;
    });

    DB::transaction(static function () use ($page, &$dispatched): void {
        DiscardDraft::run($page);

        expect($dispatched)->toBe(0);
    });

    expect($dispatched)->toBe(1);
});

it('keeps a change to another strip that was stored after the page was loaded', function (Closure $change, Closure $changed): void {
    $page = Page::factory()->published()->withStrips([storedStrip(id: 'a'), storedStrip(id: 'b')])->create();
    $loaded = Page::query()->findOrFail($page->id);

    UpdateStrip::run(Page::query()->findOrFail($page->id), 'b', ['kaarten' => [['titel' => 'Elders']]], chooseLevels: true);
    $change($loaded);

    expect(firstCardTitle($page, 'b'))->toBe('Elders')
        ->and($changed(storedDraft($page)))->toBeTrue();
})->with([
    'AddStrip' => [
        static fn (Page $page): Strip => AddStrip::run($page, 'cards', 0),
        static fn (PageStrips $strips): bool => $strips->count() === 3,
    ],
    'UpdateStrip' => [
        static fn (Page $page): Strip => UpdateStrip::run($page, 'a', ['kaarten' => [['titel' => 'Hier']]], chooseLevels: true),
        static fn (PageStrips $strips): bool => ($strips->find('a')?->values['kaarten'][0]['titel'] ?? null) === 'Hier',
    ],
    'MoveStrip' => [
        static fn (Page $page): mixed => MoveStrip::run($page, 'a', 1),
        static fn (PageStrips $strips): bool => draftStripIds($strips) === ['b', 'a'],
    ],
    'RemoveStrip' => [
        static fn (Page $page): Strip => RemoveStrip::run($page, 'a'),
        static fn (PageStrips $strips): bool => draftStripIds($strips) === ['b'],
    ],
    'SetStripVisibility' => [
        static fn (Page $page): Strip => SetStripVisibility::run($page, 'a', false),
        static fn (PageStrips $strips): bool => $strips->find('a')?->visible === false,
    ],
    'RestoreStrip' => [
        static fn (Page $page): Strip => RestoreStrip::run($page, new Strip('c', 'cards', true, ['kaarten' => []]), 0, chooseLevels: true),
        static fn (PageStrips $strips): bool => draftStripIds($strips) === ['c', 'a', 'b'],
    ],
]);

it('drops a draft that no longer changes anything', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip(id: 'a'), storedStrip(id: 'b')])
        ->withDraft([storedStrip(id: 'b'), storedStrip(id: 'a')])
        ->create();

    ChangeDraft::run($page, static function (PageStrips $strips, Page $page): void {
        $page->draft_strips = $strips->move('a', 0)->toArray();
    });

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBeNull()
        ->and($page->draft_strips)->toBeNull();
});

it('keeps a draft that still changes something', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip(id: 'a'), storedStrip(id: 'b')])->create();

    ChangeDraft::run($page, static function (PageStrips $strips, Page $page): void {
        $page->draft_strips = $strips->move('a', 1)->toArray();
    });

    expect(draftStripIds(storedDraft($page)))->toBe(['b', 'a']);
});

it('drops the draft of a page that was never published once it holds what the page holds', function (): void {
    $page = Page::factory()->withDraft([storedStrip(id: 'a')])->create();

    ChangeDraft::run($page, static function (PageStrips $strips, Page $page): void {
        $page->draft_strips = $strips->remove('a')->toArray();
    });

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBeNull();
});

it('publishes the draft as it is stored, not as the loaded page holds it', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip(id: 'a')])->create();
    $loaded = Page::query()->findOrFail($page->id);

    UpdateStrip::run(Page::query()->findOrFail($page->id), 'a', ['kaarten' => [['titel' => 'Nieuw']]], chooseLevels: true);
    PublishPage::run($loaded);

    $stored = Page::query()->findOrFail($page->id);

    expect($stored->draft_strips)->toBeNull()
        ->and(PageStrips::fromArray($stored->strips)->find('a')?->values['kaarten'][0]['titel'] ?? null)->toBe('Nieuw');
});

it('discards the draft as it is stored, not as the loaded page holds it', function (): void {
    Event::fake([DraftDiscarded::class]);
    $page = Page::factory()->published()->withStrips([storedStrip(id: 'a')])->create();
    $loaded = Page::query()->findOrFail($page->id);

    UpdateStrip::run(Page::query()->findOrFail($page->id), 'a', ['kaarten' => [['titel' => 'Nieuw']]], chooseLevels: true);
    DiscardDraft::run($loaded);

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBeNull();
    Event::assertDispatched(DraftDiscarded::class);
});
