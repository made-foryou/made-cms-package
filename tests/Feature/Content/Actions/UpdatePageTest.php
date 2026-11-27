<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\UpdatePage;
use Made\CmsPackage\Content\Data\PageChanges;
use Made\CmsPackage\Content\Data\PageTypeChoice;
use Made\CmsPackage\Content\Data\ScheduleChoice;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Events\PageUpdated;
use Made\CmsPackage\Content\Events\PageVisibilityChanged;
use Made\CmsPackage\Content\Events\PublicationScheduled;
use Made\CmsPackage\Content\Exceptions\CircularPageMove;
use Made\CmsPackage\Content\Exceptions\HomePageProtected;
use Made\CmsPackage\Content\Exceptions\InvalidPageSlug;
use Made\CmsPackage\Content\Exceptions\PagePathTaken;
use Made\CmsPackage\Content\Exceptions\PagePathTooLong;
use Made\CmsPackage\Content\Exceptions\PublicationMomentPassed;
use Made\CmsPackage\Content\Exceptions\UnknownPageType;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Tests\Fixtures\PageTypes\InlinePageType;

it('changes the title and the menu flag without touching the address', function (): void {
    $page = Page::factory()->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);

    UpdatePage::run($page, new PageChanges(title: ' Wie we zijn ', slug: 'over-ons', parentId: null, inMenu: false));

    expect($page->refresh()->title)->toBe('Wie we zijn')
        ->and($page->in_menu)->toBeFalse()
        ->and($page->route?->path)->toBe('/over-ons');
});

it('changes the slug and rewrites the addresses of the page and the pages under it', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);

    UpdatePage::run($about, new PageChanges(title: 'Vereniging', slug: 'vereniging', parentId: null, inMenu: true));

    expect($about->refresh()->slug)->toBe('vereniging')
        ->and($about->route?->path)->toBe('/vereniging')
        ->and($team->refresh()->route?->path)->toBe('/vereniging/team');
});

it('moves the page under another parent, last among its new siblings', function (): void {
    $about = Page::factory()->atPosition(0)->at('/over-ons')->create(['slug' => 'over-ons']);
    $club = Page::factory()->atPosition(1)->at('/vereniging')->create(['slug' => 'vereniging']);
    Page::factory()->under($club)->at('/vereniging/bestuur')->create(['slug' => 'bestuur']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);

    UpdatePage::run($team, new PageChanges(title: 'Team', slug: 'team', parentId: $club->id, inMenu: true));

    expect($team->refresh()->parent_id)->toBe($club->id)
        ->and($team->position)->toBe(1)
        ->and($team->route?->path)->toBe('/vereniging/team');
});

it('moves the page and changes its slug at once', function (): void {
    $about = Page::factory()->atPosition(0)->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);

    UpdatePage::run($team, new PageChanges(title: 'Ons team', slug: 'ons-team', parentId: null, inMenu: true));

    expect($team->refresh()->route?->path)->toBe('/ons-team');
});

it('derives the slug from the title when the slug is cleared', function (): void {
    $page = Page::factory()->at('/oud')->create(['slug' => 'oud']);

    UpdatePage::run($page, new PageChanges(title: 'Nieuwe naam', slug: '', parentId: null, inMenu: true));

    expect($page->refresh()->route?->path)->toBe('/nieuwe-naam');
});

it('refuses a slug with nothing left', function (): void {
    $page = Page::factory()->at('/oud')->create(['slug' => 'oud']);

    expect(fn (): Page => UpdatePage::run($page, new PageChanges(title: '???', slug: null, parentId: null, inMenu: true)))
        ->toThrow(InvalidPageSlug::class);
});

it('refuses a slug whose address is taken and changes nothing', function (): void {
    Page::factory()->at('/contact')->create(['slug' => 'contact']);
    $page = Page::factory()->at('/oud')->create(['title' => 'Oud', 'slug' => 'oud']);

    expect(fn (): Page => UpdatePage::run($page, new PageChanges(title: 'Nieuw', slug: 'contact', parentId: null, inMenu: true)))
        ->toThrow(PagePathTaken::class);

    expect($page->refresh()->title)->toBe('Oud')
        ->and($page->route?->path)->toBe('/oud');
});

it('allows a slug that a page under another parent uses', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);
    $page = Page::factory()->at('/oud')->create(['slug' => 'oud']);

    UpdatePage::run($page, new PageChanges(title: 'Team', slug: 'team', parentId: null, inMenu: true));

    expect($page->refresh()->route?->path)->toBe('/team');
});

it('refuses a parent under the page itself', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);

    expect(fn (): Page => UpdatePage::run($about, new PageChanges(title: 'Over ons', slug: 'over-ons', parentId: $team->id, inMenu: true)))
        ->toThrow(CircularPageMove::class);
});

it('keeps the address of the home page', function (): void {
    $home = Page::factory()->at('/')->create(['slug' => 'home']);

    expect(fn (): Page => UpdatePage::run($home, new PageChanges(title: 'Home', slug: 'start', parentId: null, inMenu: true)))
        ->toThrow(HomePageProtected::class);
});

it('changes the title of the home page', function (): void {
    $home = Page::factory()->at('/')->create(['title' => 'Home', 'slug' => 'home']);

    UpdatePage::run($home, new PageChanges(title: 'Welkom', slug: 'home', parentId: null, inMenu: true));

    expect($home->refresh()->title)->toBe('Welkom')
        ->and($home->route?->path)->toBe('/');
});

it('does nothing when nothing changes', function (): void {
    Event::fake([PageUpdated::class]);
    $page = Page::factory()->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);

    UpdatePage::run($page, new PageChanges(title: 'Over ons', slug: 'over-ons', parentId: null, inMenu: true));

    Event::assertNotDispatched(PageUpdated::class);
});

it('keeps the status and the strips', function (): void {
    $strips = [storedStrip(id: 'a')];
    $page = Page::factory()->published()->withStrips($strips)->at('/over-ons')->create(['slug' => 'over-ons']);

    UpdatePage::run($page, new PageChanges(title: 'Anders', slug: 'anders', parentId: null, inMenu: true));

    expect($page->refresh()->status)->toBe(PageStatus::Published)
        ->and($page->strips)->toBeStoredAs($strips)
        ->and($page->draft_strips)->toBeNull();
});

it('dispatches an event with the old and the new address', function (): void {
    Event::fake([PageUpdated::class]);
    $page = Page::factory()->at('/oud')->create(['slug' => 'oud']);

    UpdatePage::run($page, new PageChanges(title: 'Nieuw', slug: 'nieuw', parentId: null, inMenu: true));

    Event::assertDispatched(PageUpdated::class, fn (PageUpdated $event): bool => $event->page->is($page)
        && $event->from->value === '/oud'
        && $event->to->value === '/nieuw');
});

it('hides the page from visitors together with the other changes', function (): void {
    Event::fake([PageUpdated::class, PageVisibilityChanged::class]);
    $page = Page::factory()->published()->at('/privacy')->create(['title' => 'Privacy', 'slug' => 'privacy']);

    UpdatePage::run($page, new PageChanges(title: 'Privacyverklaring', slug: 'privacy', parentId: null, inMenu: false, hidden: true));

    expect($page->refresh()->title)->toBe('Privacyverklaring')
        ->and($page->in_menu)->toBeFalse()
        ->and($page->hidden)->toBeTrue();
    Event::assertDispatchedTimes(PageUpdated::class, 1);
    Event::assertDispatchedTimes(PageVisibilityChanged::class, 1);
});

it('only tells that visitors see the page again when nothing else changed', function (): void {
    Event::fake([PageUpdated::class, PageVisibilityChanged::class]);
    $page = Page::factory()->hidden()->at('/privacy')->create(['title' => 'Privacy', 'slug' => 'privacy']);

    UpdatePage::run($page, new PageChanges(title: 'Privacy', slug: 'privacy', parentId: null, inMenu: true, hidden: false));

    expect($page->refresh()->hidden)->toBeFalse();
    Event::assertNotDispatched(PageUpdated::class);
    Event::assertDispatchedTimes(PageVisibilityChanged::class, 1);
});

it('keeps whether visitors see the page when the changes leave it out', function (): void {
    Event::fake([PageVisibilityChanged::class]);
    $page = Page::factory()->hidden()->at('/privacy')->create(['title' => 'Privacy', 'slug' => 'privacy']);

    UpdatePage::run($page, new PageChanges(title: 'Privacyverklaring', slug: 'privacy', parentId: null, inMenu: true));

    expect($page->refresh()->title)->toBe('Privacyverklaring')
        ->and($page->hidden)->toBeTrue();
    Event::assertNotDispatched(PageVisibilityChanged::class);
});

it('changes nothing of the home page when it is asked to hide it', function (): void {
    $home = Page::factory()->at('/')->create(['title' => 'Home', 'slug' => 'home']);

    expect(fn (): Page => UpdatePage::run($home, new PageChanges(title: 'Welkom', slug: 'home', parentId: null, inMenu: true, hidden: true)))
        ->toThrow(HomePageProtected::class);
    expect($home->refresh()->title)->toBe('Home')
        ->and($home->hidden)->toBeFalse();
});

it('refuses a slug that makes the address of a page under it too long and changes nothing', function (): void {
    Event::fake([PageUpdated::class]);
    $page = Page::factory()->at('/'.str_repeat('p', 10))->create(['title' => 'Pagina', 'slug' => str_repeat('p', 10)]);
    $child = Page::factory()->under($page)->at('/'.str_repeat('p', 10).'/'.str_repeat('c', 120))->create(['slug' => str_repeat('c', 120)]);
    $grandchild = Page::factory()->under($child)->at('/'.str_repeat('p', 10).'/'.str_repeat('c', 120).'/'.str_repeat('g', 120))->create(['slug' => str_repeat('g', 120)]);

    // The grandchild is 253 characters now; a slug of 13 makes it 256.
    expect(fn (): Page => UpdatePage::run($page, new PageChanges(title: 'Langer', slug: str_repeat('p', 13), parentId: null, inMenu: true)))
        ->toThrow(PagePathTooLong::class);

    $stored = Page::query()->with('route')->findOrFail($page->id);

    expect($stored->title)->toBe('Pagina')
        ->and($stored->slug)->toBe(str_repeat('p', 10))
        ->and($stored->route?->path)->toBe('/'.str_repeat('p', 10))
        ->and($child->refresh()->route?->path)->toBe('/'.str_repeat('p', 10).'/'.str_repeat('c', 120))
        ->and($grandchild->refresh()->route?->path)->toBe('/'.str_repeat('p', 10).'/'.str_repeat('c', 120).'/'.str_repeat('g', 120));
    Event::assertNotDispatched(PageUpdated::class);
});

it('refuses an address another request stores at the same moment, as a taken address', function (): void {
    Event::fake([PageUpdated::class]);
    $page = Page::factory()->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);
    claimAddressFirst();

    expect(fn (): Page => UpdatePage::run($page, new PageChanges(title: 'Vereniging', slug: 'vereniging', parentId: null, inMenu: true)))
        ->toThrow(PagePathTaken::class, 'There is already a page at [/vereniging].');

    $stored = Page::query()->with('route')->findOrFail($page->id);

    expect($stored->title)->toBe('Over ons')
        ->and($stored->route?->path)->toBe('/over-ons');
    Event::assertNotDispatched(PageUpdated::class);
});

it('refuses a parent that went under the page at the same moment', function (): void {
    Event::fake([PageUpdated::class]);
    $a = Page::factory()->atPosition(0)->at('/a')->create(['title' => 'A', 'slug' => 'a']);
    $b = Page::factory()->atPosition(1)->at('/b')->create(['slug' => 'b']);
    // Another request hangs B under A after this one found B, and commits before this one starts writing.
    $competing = true;
    DB::connection()->beforeStartingTransaction(static function () use (&$competing, $a, $b): void {
        if ($competing) {
            $competing = false;
            DB::table('cms_pages')->where('id', $b->id)->update(['parent_id' => $a->id]);
        }
    });

    expect(fn (): Page => UpdatePage::run($a, new PageChanges(title: 'A', slug: 'a', parentId: $b->id, inMenu: true)))
        ->toThrow(CircularPageMove::class);

    expect(Page::query()->findOrFail($a->id)->parent_id)->toBeNull();
    Event::assertNotDispatched(PageUpdated::class);
});

it('notes the editor who changes the page', function (): void {
    $editor = Editor::factory()->create();
    $page = Page::factory()->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);

    UpdatePage::run($page, new PageChanges(title: 'Wie we zijn', slug: 'over-ons', parentId: null, inMenu: true, editorId: $editor->id));

    expect($page->refresh()->updated_by)->toBe($editor->id);
});

it('notes the editor who only hides the page', function (): void {
    $editor = Editor::factory()->create();
    $page = Page::factory()->published()->at('/privacy')->create(['title' => 'Privacy', 'slug' => 'privacy']);

    UpdatePage::run($page, new PageChanges(title: 'Privacy', slug: 'privacy', parentId: null, inMenu: true, hidden: true, editorId: $editor->id));

    expect($page->refresh()->updated_by)->toBe($editor->id);
});

it('leaves who changed the page last when nothing changes', function (): void {
    $before = Editor::factory()->create();
    $page = Page::factory()->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons', 'updated_by' => $before->id]);

    UpdatePage::run($page, new PageChanges(title: 'Over ons', slug: 'over-ons', parentId: null, inMenu: true, editorId: Editor::factory()->create()->id));

    expect($page->refresh()->updated_by)->toBe($before->id);
});

it('changes the type of a page and keeps every strip on it', function (): void {
    useStripTypes();
    usePageTypesWith(new InlinePageType('team', ['hero']));
    $strips = [storedStrip('hero', id: 'a'), storedStrip('cards', id: 'b')];
    $page = Page::factory()->at('/teams')->withDraft($strips)->create(['slug' => 'teams']);
    Event::fake([PageUpdated::class]);

    UpdatePage::run($page, new PageChanges(title: $page->title, slug: 'teams', parentId: null, inMenu: true, type: new PageTypeChoice('team')));

    $stored = Page::query()->findOrFail($page->id);

    expect($stored->type)->toBe('team')
        ->and(array_column($stored->draft_strips ?? [], 'id'))->toBe(['a', 'b']);
    Event::assertDispatched(PageUpdated::class);
});

it('makes a page of a type an ordinary page again', function (): void {
    usePageTypes();
    $page = Page::factory()->at('/teams')->create(['slug' => 'teams', 'type' => 'team']);

    UpdatePage::run($page, new PageChanges(title: $page->title, slug: 'teams', parentId: null, inMenu: true, type: new PageTypeChoice(null)));

    expect(Page::query()->findOrFail($page->id)->type)->toBeNull();
});

it('keeps the type of a page when the changes say nothing about it', function (): void {
    usePageTypes();
    $page = Page::factory()->at('/teams')->create(['slug' => 'teams', 'type' => 'team']);

    UpdatePage::run($page, new PageChanges(title: 'Onze teams', slug: 'teams', parentId: null, inMenu: true));

    expect(Page::query()->findOrFail($page->id)->type)->toBe('team');
});

it('keeps a type the site no longer defines when the page keeps it', function (): void {
    usePageTypes();
    $page = Page::factory()->at('/teams')->create(['slug' => 'teams', 'type' => 'vervallen']);

    UpdatePage::run($page, new PageChanges(title: 'Onze teams', slug: 'teams', parentId: null, inMenu: true, type: new PageTypeChoice('vervallen')));

    expect(Page::query()->findOrFail($page->id))
        ->title->toBe('Onze teams')
        ->type->toBe('vervallen');
});

it('refuses a type the site does not define and changes nothing', function (): void {
    usePageTypes();
    $page = Page::factory()->at('/teams')->create(['title' => 'Teams', 'slug' => 'teams']);

    expect(fn (): Page => UpdatePage::run($page, new PageChanges(title: 'Onze teams', slug: 'teams', parentId: null, inMenu: true, type: new PageTypeChoice('contact'))))
        ->toThrow(UnknownPageType::class);

    expect(Page::query()->findOrFail($page->id))
        ->title->toBe('Teams')
        ->type->toBeNull();
});

it('plans the publication together with the other changes', function (): void {
    Event::fake([PageUpdated::class, PublicationScheduled::class]);
    $editor = Editor::factory()->create();
    $page = Page::factory()->published()->withDraft([])->at('/teams')->create(['title' => 'Teams', 'slug' => 'teams']);

    UpdatePage::run($page, new PageChanges(title: 'Onze teams', slug: 'teams', parentId: null, inMenu: true, editorId: $editor->id,
        schedule: new ScheduleChoice(now()->addDay()->setTime(9, 0)->toImmutable())));

    expect($page->refresh()->title)->toBe('Onze teams')
        ->and($page->publish_at?->toDateTimeString())->toBe(now()->addDay()->setTime(9, 0)->toDateTimeString())
        ->and($page->scheduled_by)->toBe($editor->id);
    Event::assertDispatchedTimes(PageUpdated::class, 1);
    Event::assertDispatchedTimes(PublicationScheduled::class, 1);
});

it('only plans the publication when nothing else changed', function (): void {
    Event::fake([PageUpdated::class]);
    $page = Page::factory()->at('/teams')->create(['title' => 'Teams', 'slug' => 'teams']);

    UpdatePage::run($page, new PageChanges(title: 'Teams', slug: 'teams', parentId: null, inMenu: true,
        schedule: new ScheduleChoice(now()->addDay()->setTime(9, 0)->toImmutable())));

    expect($page->refresh()->publish_at)->not->toBeNull();
    Event::assertNotDispatched(PageUpdated::class);
});

it('takes the planned publication away', function (): void {
    $page = Page::factory()->at('/teams')->scheduledAt(now()->addDay())->create(['title' => 'Teams', 'slug' => 'teams']);

    UpdatePage::run($page, new PageChanges(title: 'Teams', slug: 'teams', parentId: null, inMenu: true, schedule: new ScheduleChoice(null)));

    expect($page->refresh()->publish_at)->toBeNull();
});

it('keeps the planned publication when the changes leave it out', function (): void {
    $moment = CarbonImmutable::parse('2026-11-28 09:00:00');
    $page = Page::factory()->at('/teams')->scheduledAt($moment)->create(['title' => 'Teams', 'slug' => 'teams']);

    UpdatePage::run($page, new PageChanges(title: 'Onze teams', slug: 'teams', parentId: null, inMenu: true));

    expect($page->refresh()->publish_at?->toDateTimeString())->toBe('2026-11-28 09:00:00');
});

it('refuses a moment that passed and changes nothing', function (): void {
    $page = Page::factory()->at('/teams')->create(['title' => 'Teams', 'slug' => 'teams']);

    expect(fn (): Page => UpdatePage::run($page, new PageChanges(title: 'Onze teams', slug: 'teams', parentId: null, inMenu: true,
        schedule: new ScheduleChoice(now()->subHour()->toImmutable()))))->toThrow(PublicationMomentPassed::class);
    expect($page->refresh()->title)->toBe('Teams')
        ->and($page->publish_at)->toBeNull();
});
