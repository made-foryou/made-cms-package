<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\ChangeDraft;
use Made\CmsPackage\Content\Actions\PublishPage;
use Made\CmsPackage\Content\Data\PageStrips;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Events\PagePublished;
use Made\CmsPackage\Content\Exceptions\PageAlreadyPublished;
use Made\CmsPackage\Content\Exceptions\PublicationRefused;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\PageVersion;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Tests\Fixtures\Strips\Banner;
use Made\CmsPackage\Tests\Fixtures\Strips\Downloads;

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

    expect($stored->strips)->toBeStoredAs($draft)
        ->and($stored->draft_strips)->toBeNull();
});

it('publishes the draft of a page that is already published', function (): void {
    $this->freezeSecond();
    $draft = [storedStrip('cards', id: 'new')];
    $page = Page::factory()->published()->withStrips([storedStrip('cards', id: 'old')])->withDraft($draft)->create(['published_at' => now()->subWeek()]);

    PublishPage::run($page);

    $stored = Page::query()->findOrFail($page->id);

    expect($stored->strips)->toBeStoredAs($draft)
        ->and($stored->draft_strips)->toBeNull()
        ->and($stored->status)->toBe(PageStatus::Published)
        ->and($stored->published_at?->equalTo(now()))->toBeTrue();
});

it('keeps every publication as a version of the page', function (): void {
    $this->freezeSecond();
    $editor = Editor::factory()->create();
    $first = [storedStrip('hero', publishableHero(), id: 'a')];
    $second = [storedStrip('hero', publishableHero(), id: 'a'), storedStrip('cards', id: 'b')];
    $page = Page::factory()->withDraft($first)->create();

    PublishPage::run($page, $editor->id);
    ChangeDraft::run($page, function (PageStrips $strips, Page $page) use ($second): void {
        $page->draft_strips = $second;
    });
    PublishPage::run($page);

    $versions = PageVersion::query()->ofPage($page)->newestFirst()->get();

    expect($versions->pluck('number')->all())->toBe([2, 1])
        ->and($versions[1]->strips)->toBeStoredAs($first)
        ->and($versions[1]->published_by)->toBe($editor->id)
        ->and($versions[1]->changes['first'])->toBeTrue()
        ->and($versions[0]->strips)->toBeStoredAs($second)
        ->and($versions[0]->changes['added'])->toBe(['b'])
        ->and($versions[0]->published_by)->toBeNull()
        ->and($versions[0]->published_at->equalTo(Page::query()->findOrFail($page->id)->published_at))->toBeTrue();
});

it('keeps no version of a publication it refuses', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip('hero', publishableHero(), id: 'a')])->create();
    $broken = Page::factory()->withDraft([storedStrip('hero', ['kop' => null], id: 'b')])->create();

    expect(fn (): mixed => PublishPage::run($page))->toThrow(PageAlreadyPublished::class)
        ->and(fn (): mixed => PublishPage::run($broken))->toThrow(ValidationException::class)
        ->and(PageVersion::query()->count())->toBe(0);
});

it('takes the planned publication and the note of a stopped one along', function (): void {
    $page = Page::factory()->withDraft([storedStrip('hero', publishableHero(), id: 'a')])
        ->scheduledAt(now()->addDay(), Editor::factory()->create())
        ->publicationFailedAt(now()->subDay())
        ->create();

    PublishPage::run($page);

    $stored = Page::query()->findOrFail($page->id);

    expect($stored->publish_at)->toBeNull()
        ->and($stored->scheduled_by)->toBeNull()
        ->and($stored->publish_failed_at)->toBeNull();
});

it('keeps the planned publication when it refuses the draft', function (): void {
    $page = Page::factory()->withDraft([storedStrip('hero', ['kop' => null], id: 'a')])->scheduledAt(now()->addDay())->create();

    expect(fn (): mixed => PublishPage::run($page))->toThrow(PublicationRefused::class)
        ->and($page->refresh()->publish_at)->not->toBeNull();
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

    expect($stored->strips)->toBeStoredAs($published)
        ->and($stored->draft_strips)->toBeStoredAs($draft);
    Event::assertNotDispatched(PagePublished::class);
})->with(['visible' => true, 'hidden' => false]);

it('checks a field the strip does not hold yet as its default, as the site renders it', function (): void {
    $draft = [storedStrip('hero', ['knoppen' => publishableHero()['knoppen']], id: 'a')];
    $page = Page::factory()->withDraft($draft)->create();

    PublishPage::run($page);

    expect(Page::query()->findOrFail($page->id)->strips)->toBeStoredAs($draft);
});

it('publishes a strip of a type that no longer exists without checking it', function (): void {
    $draft = [storedStrip('oud', ['x' => 1], id: 'old'), storedStrip('cards', id: 'a')];
    $page = Page::factory()->withDraft($draft)->create();

    PublishPage::run($page);

    expect(Page::query()->findOrFail($page->id)->strips)->toBeStoredAs($draft);
});

it('publishes a heading stored as text, or with a level its field no longer offers', function (): void {
    app()->instance(StripTypes::class, new StripTypes([new Banner]));
    $draft = [
        storedStrip('banner', ['kop' => 'Welkom', 'kaarten' => [['titel' => 'Spelen']]], id: 'a'),
        storedStrip('banner', ['kop' => ['text' => 'Agenda', 'tag' => 'h5'], 'kaarten' => [['titel' => ['text' => 'Teams', 'tag' => 'h6']]]], id: 'b'),
    ];
    $page = Page::factory()->withDraft($draft)->create();

    PublishPage::run($page);

    expect(Page::query()->findOrFail($page->id)->strips)->toBeStoredAs($draft);
});

it('refuses a draft with an image whose file is gone or is no image, on the field of the strip', function (): void {
    $gone = Media::factory()->create();
    $document = Media::factory()->document()->create();
    $draft = [
        storedStrip('hero', [...publishableHero(), 'foto' => $gone->id], id: 'a'),
        storedStrip('cards', ['kaarten' => [['titel' => 'Een', 'icoon' => $document->id]]], id: 'b'),
    ];
    $page = Page::factory()->withDraft($draft)->create();
    $gone->delete();

    try {
        PublishPage::run($page);
        $this->fail('The draft was published.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'strips.a.foto' => ['De gekozen afbeelding bij Foto bestaat niet meer.'],
            'strips.b.kaarten.0.icoon' => ['Het gekozen bestand bij Icoon is geen afbeelding.'],
        ]);
    }

    expect(Page::query()->findOrFail($page->id)->strips)->toBe([]);
});

it('refuses a draft with a file that is gone or of a kind its field does not take, and publishes one that fits', function (): void {
    app()->instance(StripTypes::class, new StripTypes([new Downloads]));
    $gone = Media::factory()->document()->create();
    $video = Media::factory()->video()->create();
    $schema = Media::factory()->document()->create();
    $page = Page::factory()->withDraft([
        storedStrip('downloads', ['video' => $video->id, 'bestanden' => [['id' => '9b3e1a52-7c0d-4f8e-9a61-2d5c8e7f4b10', 'bestand' => $gone->id], ['id' => '9b3e1a52-7c0d-4f8e-9a61-2d5c8e7f4b11', 'bestand' => $video->id]]], id: 'a'),
    ])->create();
    $gone->delete();

    try {
        PublishPage::run($page);
        $this->fail('The draft was published.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'strips.a.bestanden.0.bestand' => ['Het gekozen bestand bij Bestand bestaat niet meer.'],
            'strips.a.bestanden.1.bestand' => ['Het gekozen bestand bij Bestand is geen document.'],
        ]);
    }

    $draft = [storedStrip('downloads', ['video' => $video->id, 'bestanden' => [['id' => '9b3e1a52-7c0d-4f8e-9a61-2d5c8e7f4b10', 'bestand' => $schema->id]]], id: 'a')];
    $page = Page::factory()->withDraft($draft)->create();

    PublishPage::run($page);

    expect(Page::query()->findOrFail($page->id)->strips)->toBeStoredAs($draft);
});

it('publishes an image of the library as the id of its file', function (): void {
    $media = Media::factory()->create();
    $draft = [storedStrip('hero', [...publishableHero(), 'foto' => $media->id], id: 'a')];
    $page = Page::factory()->withDraft($draft)->create();

    PublishPage::run($page);

    expect(Page::query()->findOrFail($page->id)->strips)->toBeStoredAs($draft);
});
