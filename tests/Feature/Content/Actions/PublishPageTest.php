<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Content\Actions\PublishPage;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Events\PagePublished;
use Made\CmsPackage\Content\Exceptions\PageAlreadyPublished;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
});

/**
 * @return array<string, mixed>
 */
function publishableHero(): array
{
    return [
        'kop' => 'TTV Merwestad',
        'knoppen' => [['tekst' => 'Lid worden', 'gaat_naar' => ['url' => '/lid-worden']]],
    ];
}

it('publishes a draft page', function (): void {
    $this->freezeSecond();
    $page = Page::factory()->create();

    $published = PublishPage::run($page);

    $stored = Page::query()->findOrFail($page->id);

    expect($published->is($page))->toBeTrue()
        ->and($stored->status)->toBe(PageStatus::Published)
        ->and($stored->published_at?->equalTo(now()))->toBeTrue();
});

it('makes the draft strips the published strips', function (): void {
    $draft = [storedStrip('hero', publishableHero(), id: 'a'), storedStrip('cards', ['kaarten' => []], visible: false, id: 'b')];
    $page = Page::factory()->withDraft($draft)->create();

    PublishPage::run($page);

    $stored = Page::query()->findOrFail($page->id);

    expect($stored->strips)->toBe($draft)
        ->and($stored->draft_strips)->toBeNull();
});

it('publishes the draft of a page that is already published', function (): void {
    $this->freezeSecond();
    $draft = [storedStrip('cards', id: 'new')];
    $page = Page::factory()->published()->withStrips([storedStrip('cards', id: 'old')])->withDraft($draft)->create(['published_at' => now()->subWeek()]);

    PublishPage::run($page);

    $stored = Page::query()->findOrFail($page->id);

    expect($stored->strips)->toBe($draft)
        ->and($stored->draft_strips)->toBeNull()
        ->and($stored->status)->toBe(PageStatus::Published)
        ->and($stored->published_at?->equalTo(now()))->toBeTrue();
});

it('dispatches an event for the published page', function (): void {
    Event::fake([PagePublished::class]);
    $page = Page::factory()->create();

    PublishPage::run($page);

    Event::assertDispatched(PagePublished::class, fn (PagePublished $event): bool => $event->page->is($page));
});

it('refuses to publish a page that is already published without a draft', function (): void {
    Event::fake([PagePublished::class]);
    $page = Page::factory()->published()->create();

    expect(fn (): mixed => PublishPage::run($page))->toThrow(PageAlreadyPublished::class);

    Event::assertNotDispatched(PagePublished::class);
});

it('refuses a draft with a strip that breaks its definition, also when it is hidden', function (bool $visible): void {
    Event::fake([PagePublished::class]);
    $published = [storedStrip('hero', publishableHero(), id: 'live')];
    $draft = [storedStrip('hero', publishableHero(), id: 'ok'), storedStrip('hero', ['kop' => null, 'knoppen' => []], visible: $visible, id: 'broken')];
    $page = Page::factory()->published()->withStrips($published)->withDraft($draft)->create();

    try {
        PublishPage::run($page);
        $this->fail('The draft was published.');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toBe(['strips.broken.kop', 'strips.broken.knoppen'])
            ->and($exception->errors()['strips.broken.kop'][0] ?? '')->toBe('Vul Kop in.');
    }

    $stored = Page::query()->findOrFail($page->id);

    expect($stored->strips)->toBe($published)
        ->and($stored->draft_strips)->toBe($draft);
    Event::assertNotDispatched(PagePublished::class);
})->with(['visible' => true, 'hidden' => false]);

it('publishes a strip of a type that no longer exists without checking it', function (): void {
    $draft = [storedStrip('oud', ['x' => 1], id: 'old'), storedStrip('cards', id: 'a')];
    $page = Page::factory()->withDraft($draft)->create();

    PublishPage::run($page);

    expect(Page::query()->findOrFail($page->id)->strips)->toBe($draft);
});
