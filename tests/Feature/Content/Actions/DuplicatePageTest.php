<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\DeletePage;
use Made\CmsPackage\Content\Actions\DuplicatePage;
use Made\CmsPackage\Content\Data\PagePath;
use Made\CmsPackage\Content\Data\PageSlug;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Enums\UsageVersion;
use Made\CmsPackage\Content\Events\PageCreated;
use Made\CmsPackage\Content\Events\PageDuplicated;
use Made\CmsPackage\Content\Exceptions\PagePathTaken;
use Made\CmsPackage\Content\Exceptions\PagePathTooLong;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Route;

beforeEach(function (): void {
    useStripTypes();
});

/**
 * The pages under a parent in the order of the menu, as "position title".
 *
 * @return list<string>
 */
function duplicatedSiblings(?Page $parent): array
{
    return Page::query()->childrenOf($parent)->inMenuOrder()->get()
        ->map(static fn (Page $page): string => "{$page->position} {$page->title}")
        ->values()
        ->all();
}

/**
 * The stored strips without their ids, to compare a copy with its original.
 *
 * @param  list<array<string, mixed>>  $strips
 * @return list<array<string, mixed>>
 */
function withoutStripIds(array $strips): array
{
    return array_map(static fn (array $strip): array => [...$strip, 'id' => null], $strips);
}

it('puts a draft copy right after the original, under the same parent', function (): void {
    $parent = Page::factory()->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);
    Page::factory()->under($parent)->atPosition(0)->at('/over-ons/bestuur')->create(['title' => 'Bestuur', 'slug' => 'bestuur']);
    $team = Page::factory()->under($parent)->atPosition(1)->published()->at('/over-ons/team')->create(['title' => 'Team', 'slug' => 'team']);
    Page::factory()->under($parent)->atPosition(2)->at('/over-ons/contact')->create(['title' => 'Contact', 'slug' => 'contact']);

    $copy = DuplicatePage::run($team);

    $stored = Page::query()->with('route')->findOrFail($copy->id);

    expect($stored->title)->toBe('Team (kopie)')
        ->and($stored->slug)->toBe('team-kopie')
        ->and($stored->route?->path)->toBe('/over-ons/team-kopie')
        ->and($stored->parent_id)->toBe($parent->id)
        ->and($stored->status)->toBe(PageStatus::Draft)
        ->and($stored->published_at)->toBeNull()
        ->and($stored->strips)->toBe([])
        ->and($copy->route?->path)->toBe('/over-ons/team-kopie')
        ->and(duplicatedSiblings($parent))->toBe(['0 Bestuur', '1 Team', '2 Team (kopie)', '3 Contact']);
});

it('leaves the pages under the original where they are', function (): void {
    $team = Page::factory()->at('/team')->create(['title' => 'Team', 'slug' => 'team']);
    Page::factory()->under($team)->at('/team/heren')->create(['title' => 'Heren', 'slug' => 'heren']);

    $copy = DuplicatePage::run($team);

    expect(Page::query()->count())->toBe(3)
        ->and(Page::query()->childrenOf($copy)->exists())->toBeFalse()
        ->and(duplicatedSiblings($team))->toBe(['0 Heren']);
});

it('numbers the address of a copy when that address is taken', function (): void {
    $team = Page::factory()->at('/team')->create(['title' => 'Team', 'slug' => 'team']);

    expect(DuplicatePage::run($team)->route?->path)->toBe('/team-kopie')
        ->and(DuplicatePage::run($team)->route?->path)->toBe('/team-kopie-2')
        ->and(DuplicatePage::run($team)->route?->path)->toBe('/team-kopie-3');
});

it('takes the address a deleted page left', function (): void {
    $team = Page::factory()->at('/team')->create(['title' => 'Team', 'slug' => 'team']);
    DeletePage::run(DuplicatePage::run($team));

    expect(DuplicatePage::run($team)->route?->path)->toBe('/team-kopie');
});

it('shortens a long slug so that the copy fits', function (): void {
    $slug = str_repeat('a', 118);
    $page = Page::factory()->at('/'.$slug)->create(['slug' => $slug]);

    $first = DuplicatePage::run($page);
    $second = DuplicatePage::run($page);

    expect($first->slug)->toBe(str_repeat('a', 114).'-kopie')
        ->and(mb_strlen($first->slug))->toBe(PageSlug::MAX_LENGTH)
        ->and($second->slug)->toBe(str_repeat('a', 112).'-kopie-2');
});

it('shortens the slug so that the address of the copy fits', function (): void {
    $parentPath = '/'.str_repeat('a', 120).'/'.str_repeat('b', 120);
    $parent = Page::factory()->at($parentPath)->create(['slug' => str_repeat('b', 120)]);
    $page = Page::factory()->under($parent)->at($parentPath.'/'.str_repeat('c', 12))->create(['slug' => str_repeat('c', 12)]);

    $copy = DuplicatePage::run($page);

    expect($copy->route?->path)->toBe($parentPath.'/cccccc-kopie')
        ->and(mb_strlen((string) $copy->route?->path))->toBe(PagePath::MAX_LENGTH);
});

it('refuses a copy whose address cannot fit and stores nothing', function (): void {
    Event::fake([PageDuplicated::class]);
    $parentPath = '/'.str_repeat('a', 120).'/'.str_repeat('b', 120).'/'.str_repeat('d', 6);
    $parent = Page::factory()->at($parentPath)->create(['slug' => str_repeat('d', 6)]);
    $page = Page::factory()->under($parent)->at($parentPath.'/c')->create(['slug' => 'c']);

    expect(fn (): Page => DuplicatePage::run($page))->toThrow(PagePathTooLong::class);

    expect(Page::query()->count())->toBe(2)
        ->and(Route::query()->count())->toBe(2);
    Event::assertNotDispatched(PageDuplicated::class);
});

it('copies the type, the meta, the visibility and the place in the menu', function (): void {
    usePageTypes();
    $page = Page::factory()->at('/team')->hidden()->inMenu(false)->withMeta('Ons team', 'Wie er spelen.')->noindex()->outOfSitemap()
        ->create(['slug' => 'team', 'type' => 'team']);

    $copy = Page::query()->findOrFail(DuplicatePage::run($page)->id);

    expect($copy->type)->toBe('team')
        ->and($copy->hidden)->toBeTrue()
        ->and($copy->in_menu)->toBeFalse()
        ->and($copy->meta_title)->toBe('Ons team')
        ->and($copy->meta_description)->toBe('Wie er spelen.')
        ->and($copy->indexable)->toBeFalse()
        ->and($copy->in_sitemap)->toBeFalse();
});

it('keeps a type the site no longer defines', function (): void {
    $page = Page::factory()->at('/team')->create(['slug' => 'team', 'type' => 'vervallen']);

    expect(Page::query()->findOrFail(DuplicatePage::run($page)->id)->type)->toBe('vervallen');
});

it('copies the share image with its place in the usage', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->at('/team')->withShareImage($media)->create(['slug' => 'team']);

    $copy = DuplicatePage::run($page);

    expect(Page::query()->findOrFail($copy->id)->share_image_id)->toBe($media->id)
        ->and(MediaUsage::query()->onPage($copy)->where('version', UsageVersion::Page->value)->value('media_id'))->toBe($media->id);
});

it('copies the draft of the original as the draft of the copy, with new strip ids', function (): void {
    $photo = Media::factory()->create();
    $draft = [
        storedStrip('hero', ['kop' => 'Welkom', 'foto' => $photo->id], id: 'a'),
        storedStrip('cards', ['kaarten' => [['id' => 'kaart-1', 'titel' => 'Een', 'icoon' => null]]], visible: false, id: 'b'),
    ];
    $page = Page::factory()->published()->withStrips([storedStrip('hero', ['kop' => 'Oud'], id: 'oud')])->withDraft($draft)->at('/team')
        ->create(['slug' => 'team']);

    $copy = Page::query()->findOrFail(DuplicatePage::run($page)->id);
    $copied = $copy->draft_strips ?? [];
    $ids = array_column($copied, 'id');

    expect($copy->strips)->toBe([])
        ->and($copy->draft_started_at)->not->toBeNull()
        ->and($ids)->toHaveCount(2)
        ->and($ids)->each->toBeUuid()
        ->and(withoutStripIds($copied))->toBeStoredAs(withoutStripIds($draft))
        ->and(MediaUsage::query()->onPage($copy)->get()->map(static fn (MediaUsage $usage): array => [$usage->version, $usage->strip_id, $usage->field, $usage->media_id])->all())
        ->toBe([[UsageVersion::Draft, $ids[0], 'foto', $photo->id]]);
});

it('copies the published strips when the original has no draft', function (): void {
    $strips = [storedStrip('hero', ['kop' => 'Welkom'], id: 'a')];
    $page = Page::factory()->published()->withStrips($strips)->at('/team')->create(['slug' => 'team']);

    $copy = Page::query()->findOrFail(DuplicatePage::run($page)->id);

    expect($copy->strips)->toBe([])
        ->and(withoutStripIds($copy->draft_strips ?? []))->toBeStoredAs(withoutStripIds($strips))
        ->and(($copy->draft_strips ?? [])[0]['id'] ?? null)->not->toBe('a');
});

it('leaves out the strips whose type left the catalogue', function (): void {
    $hero = storedStrip('hero', ['kop' => 'Welkom'], id: 'a');
    $page = Page::factory()->withDraft([$hero, storedStrip('vervallen', ['tekst' => 'Bewaard'], id: 'b')])->at('/team')->create(['slug' => 'team']);

    $copy = Page::query()->findOrFail(DuplicatePage::run($page)->id);

    expect(withoutStripIds($copy->draft_strips ?? []))->toBeStoredAs(withoutStripIds([$hero]));
});

it('gives a copy without strips no draft', function (array $strips): void {
    $page = Page::factory()->withDraft($strips)->at('/team')->create(['slug' => 'team']);

    $copy = Page::query()->findOrFail(DuplicatePage::run($page)->id);

    expect($copy->draft_strips)->toBeNull()
        ->and($copy->draft_started_at)->toBeNull();
})->with([
    'none' => [[]],
    'only of a type that left the catalogue' => [[storedStrip('vervallen', ['tekst' => 'Bewaard'], id: 'b')]],
]);

it('leaves the original as it was', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip('hero', ['kop' => 'Welkom'], id: 'a')])->withDraft([])->at('/team')
        ->create(['slug' => 'team']);
    $before = Page::query()->findOrFail($page->id)->getAttributes();
    $this->travel(1)->minutes();

    DuplicatePage::run($page, Editor::factory()->create()->id);

    expect(Page::query()->findOrFail($page->id)->getAttributes())->toBe($before);
});

it('makes an ordinary page at the top right after the home page from the home page', function (): void {
    $home = Page::factory()->at('/')->create(['title' => 'Home', 'slug' => 'home']);
    Page::factory()->atPosition(1)->at('/contact')->create(['title' => 'Contact', 'slug' => 'contact']);

    $copy = DuplicatePage::run($home);

    expect($copy->parent_id)->toBeNull()
        ->and($copy->route?->path)->toBe('/home-kopie')
        ->and(duplicatedSiblings(null))->toBe(['0 Home', '1 Home (kopie)', '2 Contact']);
});

it('puts the copy of a page under the home page at the top of the addresses', function (): void {
    $home = Page::factory()->at('/')->create(['slug' => 'home']);
    $team = Page::factory()->under($home)->at('/team')->create(['slug' => 'team']);

    $copy = DuplicatePage::run($team);

    expect($copy->parent_id)->toBe($home->id)
        ->and($copy->route?->path)->toBe('/team-kopie');
});

it('notes the editor who copies the page', function (): void {
    $editor = Editor::factory()->create();
    $page = Page::factory()->withDraft([storedStrip('hero', id: 'a')])->at('/team')->create(['slug' => 'team']);

    $copy = Page::query()->findOrFail(DuplicatePage::run($page, $editor->id)->id);

    expect($copy->created_by)->toBe($editor->id)
        ->and($copy->updated_by)->toBe($editor->id);
});

it('does not know who copied a page without an editor', function (): void {
    $page = Page::factory()->at('/team')->create(['slug' => 'team']);

    $copy = Page::query()->findOrFail(DuplicatePage::run($page)->id);

    expect($copy->created_by)->toBeNull()
        ->and($copy->updated_by)->toBeNull();
});

it('dispatches an event with the copy and the original, and no event of a new page', function (): void {
    Event::fake([PageCreated::class, PageDuplicated::class]);
    $page = Page::factory()->at('/team')->create(['slug' => 'team']);

    $copy = DuplicatePage::run($page);

    Event::assertDispatched(PageDuplicated::class, fn (PageDuplicated $event): bool => $event->page->is($copy) && $event->original->is($page));
    Event::assertNotDispatched(PageCreated::class);
});

it('refuses an address another request stores at the same moment, as a taken address', function (): void {
    Event::fake([PageDuplicated::class]);
    $page = Page::factory()->at('/team')->create(['slug' => 'team']);
    claimAddressFirst();

    expect(fn (): Page => DuplicatePage::run($page))->toThrow(PagePathTaken::class, 'There is already a page at [/team-kopie].');

    expect(Page::query()->count())->toBe(1)
        ->and(Route::query()->count())->toBe(1);
    Event::assertNotDispatched(PageDuplicated::class);
});

it('refuses a page deleted after it was loaded', function (): void {
    $page = Page::factory()->at('/team')->create(['slug' => 'team']);
    DeletePage::run(Page::query()->findOrFail($page->id));

    expect(fn (): Page => DuplicatePage::run($page))->toThrow(ModelNotFoundException::class);
});
