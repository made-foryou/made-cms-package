<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Content\Actions\PublishPage;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Events\PagePublished;
use Made\CmsPackage\Content\Exceptions\PageAlreadyPublished;
use Made\CmsPackage\Content\Models\Page;

it('publishes a draft page', function (): void {
    $this->freezeSecond();
    $page = Page::factory()->create();

    $published = PublishPage::run($page);

    $stored = Page::query()->findOrFail($page->id);

    expect($published->is($page))->toBeTrue()
        ->and($stored->status)->toBe(PageStatus::Published)
        ->and($stored->published_at?->equalTo(now()))->toBeTrue();
});

it('dispatches an event for the published page', function (): void {
    Event::fake([PagePublished::class]);
    $page = Page::factory()->create();

    PublishPage::run($page);

    Event::assertDispatched(PagePublished::class, fn (PagePublished $event): bool => $event->page->is($page));
});

it('refuses to publish a page that is already published', function (): void {
    Event::fake([PagePublished::class]);
    $page = Page::factory()->published()->create();

    expect(fn () => PublishPage::run($page))->toThrow(PageAlreadyPublished::class);

    Event::assertNotDispatched(PagePublished::class);
});
