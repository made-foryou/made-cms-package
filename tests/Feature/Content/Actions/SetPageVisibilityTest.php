<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\SetPageVisibility;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Events\PageVisibilityChanged;
use Made\CmsPackage\Content\Exceptions\HomePageProtected;
use Made\CmsPackage\Content\Models\Page;

it('hides a page from visitors and shows it again', function (): void {
    $page = Page::factory()->published()->at('/privacy')->create();

    SetPageVisibility::run($page, hidden: true);

    expect($page->refresh()->hidden)->toBeTrue();

    SetPageVisibility::run($page, hidden: false);

    expect($page->refresh()->hidden)->toBeFalse();
});

it('keeps the status, the strips, the draft and the switches of the page', function (): void {
    $strips = [storedStrip(id: 'a')];
    $page = Page::factory()->published()->inMenu()->noindex()->withStrips($strips)->withDraft([])->at('/bedankt')->create();

    SetPageVisibility::run($page, hidden: true);

    expect($page->refresh()->status)->toBe(PageStatus::Published)
        ->and($page->strips)->toBeStoredAs($strips)
        ->and($page->draft_strips)->toBe([])
        ->and($page->in_menu)->toBeTrue()
        ->and($page->indexable)->toBeFalse();
});

it('tells once that visitors see the page or not, after the commit', function (): void {
    Event::fake([PageVisibilityChanged::class]);
    $page = Page::factory()->at('/privacy')->create();

    DB::transaction(function () use ($page): void {
        SetPageVisibility::run($page, hidden: true);

        Event::assertNotDispatched(PageVisibilityChanged::class);
    });

    Event::assertDispatchedTimes(PageVisibilityChanged::class, 1);
    Event::assertDispatched(PageVisibilityChanged::class, fn (PageVisibilityChanged $event): bool => $event->page->is($page) && $event->page->hidden);
});

it('tells nothing when the page already is as asked', function (): void {
    Event::fake([PageVisibilityChanged::class]);
    $page = Page::factory()->hidden()->at('/privacy')->create();

    SetPageVisibility::run($page, hidden: true);

    Event::assertNotDispatched(PageVisibilityChanged::class);
});

it('never hides the home page', function (): void {
    $home = Page::factory()->published()->at('/')->create();

    expect(fn (): Page => SetPageVisibility::run($home, hidden: true))->toThrow(HomePageProtected::class);
    expect($home->refresh()->hidden)->toBeFalse();
});

it('shows a home page that was hidden before', function (): void {
    $home = Page::factory()->published()->hidden()->at('/')->create();

    SetPageVisibility::run($home, hidden: false);

    expect($home->refresh()->hidden)->toBeFalse();
});

it('notes the editor who hides the page', function (): void {
    $editor = Editor::factory()->create();
    $page = Page::factory()->published()->at('/privacy')->create();

    SetPageVisibility::run($page, hidden: true, editorId: $editor->id);

    expect($page->refresh()->updated_by)->toBe($editor->id);
});

it('leaves who changed the page last when it already is as asked', function (): void {
    $before = Editor::factory()->create();
    $page = Page::factory()->hidden()->at('/privacy')->create(['updated_by' => $before->id]);

    SetPageVisibility::run($page, hidden: true, editorId: Editor::factory()->create()->id);

    expect($page->refresh()->updated_by)->toBe($before->id);
});
