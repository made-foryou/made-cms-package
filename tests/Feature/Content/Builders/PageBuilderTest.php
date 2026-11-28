<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Made\CmsPackage\Content\Builders\PageBuilder;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;

it('uses the custom builder for page queries', function (): void {
    expect(Page::query())->toBeInstanceOf(PageBuilder::class);
});

it('casts the status to the page status enum', function (): void {
    $page = Page::factory()->create();

    expect($page->refresh()->status)->toBe(PageStatus::Draft);
});

it('only returns published pages', function (): void {
    $published = Page::factory()->published()->create();
    Page::factory()->create();

    expect(Page::query()->published()->pluck('id')->all())->toBe([$published->id]);
});

it('only returns draft pages', function (): void {
    Page::factory()->published()->create();
    $draft = Page::factory()->create();

    expect(Page::query()->draft()->pluck('id')->all())->toBe([$draft->id]);
});

it('orders pages by the most recent edit first', function (): void {
    $older = Page::factory()->create(['updated_at' => now()->subDays(2)]);
    $newest = Page::factory()->create(['updated_at' => now()->subMinute()]);
    $middle = Page::factory()->create(['updated_at' => now()->subDay()]);

    expect(Page::query()->recentlyEdited()->pluck('id')->all())->toBe([$newest->id, $middle->id, $older->id]);
});

it('starts a page without strips and without a draft', function (): void {
    $page = Page::factory()->create();

    $stored = Page::query()->findOrFail($page->id);

    expect($stored->strips)->toBe([])
        ->and($stored->draft_strips)->toBeNull();
});

it('starts a page made without the factory without strips', function (): void {
    $page = Page::query()->create(['title' => 'Home', 'slug' => 'home']);

    expect($page->strips)->toBe([])
        ->and(Page::query()->findOrFail($page->id)->strips)->toBe([]);
});

it('casts the published and the draft strips to arrays', function (): void {
    $strips = [storedStrip('hero', ['kop' => 'Welkom'], id: 'a')];
    $page = Page::factory()->withStrips($strips)->withDraft([])->create();

    $stored = Page::query()->findOrFail($page->id);

    expect($stored->strips)->toBeStoredAs($strips)
        ->and($stored->draft_strips)->toBe([]);
});

it('finds the page at a path', function (): void {
    Page::factory()->at('/over-ons')->create();
    $team = Page::factory()->at('/over-ons/team')->create();

    expect(Page::query()->atPath(PagePath::fromString('/over-ons/team'))->pluck('id')->all())->toBe([$team->id]);
});

it('finds no page at a path without a route', function (): void {
    Page::factory()->create(['slug' => 'over-ons']);

    expect(Page::query()->atPath(PagePath::fromString('/over-ons'))->exists())->toBeFalse();
});

it('combines the path with the published pages', function (): void {
    Page::factory()->at('/concept')->create();
    $live = Page::factory()->published()->at('/live')->create();

    expect(Page::query()->published()->atPath(PagePath::fromString('/concept'))->exists())->toBeFalse()
        ->and(Page::query()->published()->atPath(PagePath::fromString('/live'))->pluck('id')->all())->toBe([$live->id]);
});

it('lets two pages share a slug', function (): void {
    Page::factory()->at('/over-ons/team')->create(['slug' => 'team']);
    Page::factory()->at('/vereniging/team')->create(['slug' => 'team']);

    expect(Page::query()->where('slug', 'team')->count())->toBe(2);
});

it('starts a page at position 0 in the menu without a parent', function (): void {
    $page = Page::query()->create(['title' => 'Home', 'slug' => 'home'])->refresh();

    expect($page->parent_id)->toBeNull()
        ->and($page->position)->toBe(0)
        ->and($page->in_menu)->toBeTrue()
        ->and($page->deleted_at)->toBeNull();
});

it('knows its parent and its children', function (): void {
    $parent = Page::factory()->create();
    $child = Page::factory()->under($parent)->create();

    expect($child->parent?->is($parent))->toBeTrue()
        ->and($parent->children->pluck('id')->all())->toBe([$child->id]);
});

it('only returns the root pages', function (): void {
    $root = Page::factory()->create();
    Page::factory()->under($root)->create();

    expect(Page::query()->roots()->pluck('id')->all())->toBe([$root->id]);
});

it('returns the children of a page, or the root pages for none', function (): void {
    $root = Page::factory()->create();
    $child = Page::factory()->under($root)->create();

    expect(Page::query()->childrenOf($root)->pluck('id')->all())->toBe([$child->id])
        ->and(Page::query()->childrenOf(null)->pluck('id')->all())->toBe([$root->id]);
});

it('orders siblings by position and then by id', function (): void {
    $third = Page::factory()->atPosition(2)->create();
    $first = Page::factory()->atPosition(0)->create();
    $secondA = Page::factory()->atPosition(1)->create();
    $secondB = Page::factory()->atPosition(1)->create();

    expect(Page::query()->inMenuOrder()->pluck('id')->all())->toBe([$first->id, $secondA->id, $secondB->id, $third->id]);
});

it('stores whether a page is in the menu', function (): void {
    $page = Page::factory()->inMenu(false)->create();

    expect($page->refresh()->in_menu)->toBeFalse();
});

it('keeps a new page visible to visitors', function (): void {
    $page = Page::query()->create(['title' => 'Privacy', 'slug' => 'privacy']);

    expect($page->refresh()->hidden)->toBeFalse();
});

it('stores whether a page is hidden from visitors', function (): void {
    $page = Page::factory()->hidden()->create();

    expect($page->refresh()->hidden)->toBeTrue();

    $page->hidden = false;
    $page->save();

    expect($page->refresh()->hidden)->toBeFalse();
});

it('finds the pages visitors see and the hidden pages apart', function (): void {
    $about = Page::factory()->create();
    $privacy = Page::factory()->hidden()->create();

    expect(Page::query()->visible()->pluck('id')->all())->toBe([$about->id])
        ->and(Page::query()->hidden()->pluck('id')->all())->toBe([$privacy->id]);
});

it('refuses to remove a parent that still has children for good', function (): void {
    $parent = Page::factory()->create();
    Page::factory()->under($parent)->create();

    expect(fn (): ?bool => $parent->forceDelete())->toThrow(QueryException::class);
});

it('hides a deleted page from queries', function (): void {
    $page = Page::factory()->create();

    $page->delete();

    expect(Page::query()->find($page->id))->toBeNull()
        ->and(Page::query()->withTrashed()->find($page->id)?->deleted_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('hides deleted children', function (): void {
    $parent = Page::factory()->create();
    Page::factory()->under($parent)->trashed()->create();

    expect(Page::query()->childrenOf($parent)->exists())->toBeFalse();
});

it('only returns pages deleted before a moment, oldest first', function (): void {
    $older = Page::factory()->deletedAt(now()->subDays(40))->create();
    $old = Page::factory()->deletedAt(now()->subDays(31))->create();
    Page::factory()->deletedAt(now()->subDays(5))->create();
    Page::factory()->create();

    expect(Page::query()->trashedBefore(now()->subDays(30)->toImmutable())->pluck('id')->all())->toBe([$older->id, $old->id]);
});

it('only returns pages planned to go live at or before a moment, the earliest first', function (): void {
    $moment = CarbonImmutable::parse('2026-11-27 09:00:00');
    $later = Page::factory()->scheduledAt($moment)->create();
    $earlier = Page::factory()->scheduledAt($moment->subHour())->create();
    $same = Page::factory()->scheduledAt($moment->subHour())->create();
    Page::factory()->scheduledAt($moment->addMinute())->create();
    Page::factory()->create();

    expect(Page::query()->dueForPublication($moment)->pluck('id')->all())->toBe([$earlier->id, $same->id, $later->id]);
});

it('casts the planned publication of a page', function (): void {
    $page = Page::factory()->scheduledAt(CarbonImmutable::parse('2026-11-27 09:00:00'))
        ->publicationFailedAt(CarbonImmutable::parse('2026-11-20 08:00:00'))
        ->create();

    $page = Page::query()->findOrFail($page->id);

    expect($page->publish_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($page->publish_at?->toDateTimeString())->toBe('2026-11-27 09:00:00')
        ->and($page->publish_failed_at?->toDateTimeString())->toBe('2026-11-20 08:00:00')
        ->and($page->scheduled_by)->toBeNull();
});

it('orders deleted pages by the most recent deletion first', function (): void {
    $older = Page::factory()->deletedAt(now()->subDays(3))->create();
    $newest = Page::factory()->deletedAt(now()->subDay())->create();
    Page::factory()->create();

    expect(Page::query()->onlyTrashed()->recentlyDeleted()->pluck('id')->all())->toBe([$newest->id, $older->id]);
});

it('finds the pages google may show and the pages that may be in the sitemap', function (): void {
    $about = Page::factory()->create();
    $search = Page::factory()->noindex()->create();
    $privacy = Page::factory()->outOfSitemap()->create();

    expect(Page::query()->indexable()->orderBy('id')->pluck('id')->all())->toBe([$about->id, $privacy->id])
        ->and(Page::query()->inSitemap()->orderBy('id')->pluck('id')->all())->toBe([$about->id, $search->id]);
});

it('reads only what the measure of a page in Google needs, with its address', function (): void {
    Page::factory()->published()->at('/teams')->withMeta('Onze teams', 'Alle teams.')
        ->withStrips([storedStrip()])->withDraft([storedStrip()])
        ->create(['title' => 'Teams']);

    $page = Page::query()->forMeta()->sole();

    expect(array_keys($page->getAttributes()))->toEqualCanonicalizing(['id', 'title', 'status', 'hidden', 'indexable', 'meta_title', 'meta_description'])
        ->and($page->relationLoaded('route'))->toBeTrue()
        ->and($page->route?->path)->toBe('/teams');
});

it('reads only what says whether a page is in the sitemap', function (): void {
    Page::factory()->published()->at('/teams')->withStrips([storedStrip()])->withDraft([storedStrip()])->create();

    $page = Page::query()->forSitemap()->sole();

    expect(array_keys($page->getAttributes()))->toEqualCanonicalizing(['id', 'status', 'hidden', 'indexable', 'in_sitemap', 'published_at'])
        ->and($page->relationLoaded('route'))->toBeFalse();
});
