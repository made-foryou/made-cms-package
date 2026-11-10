<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Content\Actions\UpdateStrip;
use Made\CmsPackage\Content\Events\StripUpdated;
use Made\CmsPackage\Content\Exceptions\StripNotFound;
use Made\CmsPackage\Content\Exceptions\UnknownStripType;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Tests\Fixtures\Strips\Banner;

beforeEach(function (): void {
    useStripTypes();
});

/**
 * @return array<string, mixed>
 */
function heroValues(): array
{
    return [
        'kop' => 'TTV Merwestad',
        'knoppen' => [['tekst' => 'Lid worden', 'gaat_naar' => ['url' => '/lid-worden'], 'stijl' => 'primary']],
    ];
}

it('stores the validated and cleaned values in the draft', function (): void {
    $published = [storedStrip('hero', ['kop' => 'Oud'], id: 'a')];
    $page = Page::factory()->published()->withStrips($published)->create();

    $strip = UpdateStrip::run($page, 'a', [
        ...heroValues(),
        'tekst' => '<p>Wij <strong>spelen</strong>.</p><script>x</script>',
        'onbekend' => 'weg',
    ], chooseLevels: true);

    $stored = Page::query()->findOrFail($page->id);

    $itemId = $strip->values['knoppen'][0]['id'] ?? null;

    expect($itemId)->toBeUuid()
        ->and($strip->values)->toBe([
            'label' => null,
            'kop' => 'TTV Merwestad',
            'intro' => null,
            'tekst' => '<p>Wij <strong>spelen</strong>.</p>',
            'actie' => null,
            'knoppen' => [['id' => $itemId, 'tekst' => 'Lid worden', 'gaat_naar' => ['url' => '/lid-worden'], 'stijl' => 'primary']],
            'foto' => null,
        ])
        ->and($stored->draft_strips)->toBe([$strip->toArray()])
        ->and($stored->strips)->toBe($published);
});

/**
 * The stored values of a valid hero strip, with two buttons that have an id.
 *
 * @return array<string, mixed>
 */
function storedHeroValues(): array
{
    return [
        'label' => 'Tafeltennis',
        'kop' => 'TTV Merwestad',
        'intro' => null,
        'tekst' => '<p>Wij spelen.</p>',
        'actie' => null,
        'knoppen' => [
            ['id' => '0a3e5b9c-1d2f-4a6b-8c7d-9e0f1a2b3c4d', 'tekst' => 'Lid worden', 'gaat_naar' => ['url' => '/lid-worden'], 'stijl' => 'primary'],
            ['id' => '1b4f6c0d-2e3a-4b7c-9d8e-0f1a2b3c4d5e', 'tekst' => 'Contact', 'gaat_naar' => ['url' => '/contact'], 'stijl' => 'secondary'],
        ],
        'foto' => null,
    ];
}

it('changes one field and leaves the other fields of the strip alone', function (): void {
    $page = Page::factory()->withDraft([storedStrip('hero', storedHeroValues(), id: 'a')])->create();

    $strip = UpdateStrip::run($page, 'a', ['kop' => 'Welkom bij TTV'], chooseLevels: true);

    expect($strip->values)->toBe([...storedHeroValues(), 'kop' => 'Welkom bij TTV'])
        ->and(Page::query()->findOrFail($page->id)->draft_strips)->toBe([$strip->toArray()]);
});

it('starts from the published strip when the page has no draft', function (): void {
    $published = [storedStrip('hero', storedHeroValues(), id: 'a')];
    $page = Page::factory()->published()->withStrips($published)->create();

    $strip = UpdateStrip::run($page, 'a', ['intro' => 'Sinds 1962.'], chooseLevels: true);

    expect($strip->values)->toBe([...storedHeroValues(), 'intro' => 'Sinds 1962.'])
        ->and(Page::query()->findOrFail($page->id)->strips)->toBe($published);
});

it('drops the draft once a field is changed back', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip('hero', storedHeroValues(), id: 'a')])->create();

    UpdateStrip::run($page, 'a', ['kop' => 'Welkom bij TTV'], chooseLevels: true);

    expect($page->draft_strips)->not->toBeNull();

    UpdateStrip::run($page, 'a', ['kop' => 'TTV Merwestad'], chooseLevels: true);

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBeNull();
});

it('answers the strip as the published page holds it once the draft falls away', function (): void {
    $published = [storedStrip('hero', storedHeroValues(), id: 'a')];
    $page = Page::factory()->published()->withStrips($published)->create();

    /** @var list<array<string, mixed>> $buttons */
    $buttons = storedHeroValues()['knoppen'];

    UpdateStrip::run($page, 'a', ['knoppen' => [$buttons[0]]], chooseLevels: true);
    // "Ongedaan maken" brings the second button back; the draft no longer knows its id, so it gets a new one.
    $strip = UpdateStrip::run($page, 'a', ['knoppen' => $buttons], chooseLevels: true);

    $stored = Page::query()->findOrFail($page->id);

    expect($stored->draft_strips)->toBeNull()
        ->and($stored->strips)->toBe($published)
        ->and($strip->toArray())->toBe($published[0]);
});

it('clears a field that it gets as null', function (): void {
    $page = Page::factory()->withDraft([storedStrip('hero', storedHeroValues(), id: 'a')])->create();

    $strip = UpdateStrip::run($page, 'a', ['label' => null, 'tekst' => null], chooseLevels: true);

    expect($strip->values['label'])->toBeNull()
        ->and($strip->values['tekst'])->toBeNull()
        ->and($strip->values['kop'])->toBe('TTV Merwestad');
});

it('merges the items of a list by their id', function (): void {
    $page = Page::factory()->withDraft([storedStrip('hero', storedHeroValues(), id: 'a')])->create();

    $strip = UpdateStrip::run($page, 'a', ['knoppen' => [
        ['tekst' => 'Agenda', 'gaat_naar' => ['url' => '/agenda']],
        ['id' => '0a3e5b9c-1d2f-4a6b-8c7d-9e0f1a2b3c4d', 'tekst' => 'Word lid'],
    ]], chooseLevels: true);

    /** @var list<array<string, mixed>> $knoppen */
    $knoppen = $strip->values['knoppen'];

    expect($knoppen[0]['id'] ?? null)->toBeUuid()
        ->and($knoppen[0]['id'] ?? null)->not->toBeIn(['0a3e5b9c-1d2f-4a6b-8c7d-9e0f1a2b3c4d', '1b4f6c0d-2e3a-4b7c-9d8e-0f1a2b3c4d5e'])
        ->and($knoppen)->toBe([
            ['id' => $knoppen[0]['id'] ?? null, 'tekst' => 'Agenda', 'gaat_naar' => ['url' => '/agenda']],
            ['id' => '0a3e5b9c-1d2f-4a6b-8c7d-9e0f1a2b3c4d', 'tekst' => 'Word lid', 'gaat_naar' => ['url' => '/lid-worden'], 'stijl' => 'primary'],
        ]);
});

it('keeps the ids of the items when other fields change', function (): void {
    $page = Page::factory()->withDraft([storedStrip('hero', id: 'a')])->create();
    $first = UpdateStrip::run($page, 'a', heroValues(), chooseLevels: true);

    $second = UpdateStrip::run($page, 'a', ['kop' => 'Nieuw'], chooseLevels: true);

    expect($second->values['knoppen'])->toBe($first->values['knoppen']);
});

it('checks the merged values, also the fields it does not get', function (): void {
    Event::fake([StripUpdated::class]);
    $draft = [storedStrip('hero', [...storedHeroValues(), 'kop' => null], id: 'a')];
    $page = Page::factory()->withDraft($draft)->create();

    try {
        UpdateStrip::run($page, 'a', ['intro' => 'Sinds 1962.'], chooseLevels: true);
        $this->fail('The values were accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('kop')->not->toHaveKey('intro');
    }

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBe($draft);
    Event::assertNotDispatched(StripUpdated::class);
});

it('drops stored values of fields the type no longer has', function (): void {
    $page = Page::factory()->withDraft([storedStrip('hero', [...storedHeroValues(), 'oud' => 'x'], id: 'a')])->create();

    expect(UpdateStrip::run($page, 'a', ['kop' => 'Nieuw'], chooseLevels: true)->values)->not->toHaveKey('oud');
});

it('keeps the id, type and visibility of the strip', function (): void {
    $page = Page::factory()->withDraft([storedStrip('hero', visible: false, id: 'a')])->create();

    $strip = UpdateStrip::run($page, 'a', heroValues(), chooseLevels: true);

    expect($strip->id)->toBe('a')
        ->and($strip->type)->toBe('hero')
        ->and($strip->visible)->toBeFalse();
});

it('dispatches an event for the updated strip', function (): void {
    Event::fake([StripUpdated::class]);
    $page = Page::factory()->withDraft([storedStrip('hero', id: 'a')])->create();

    UpdateStrip::run($page, 'a', heroValues(), chooseLevels: true);

    Event::assertDispatched(StripUpdated::class, fn (StripUpdated $event): bool => $event->page->is($page)
        && $event->strip->id === 'a'
        && $event->strip->values['kop'] === 'TTV Merwestad');
});

it('refuses values that break the definition', function (array $change, string $path): void {
    Event::fake([StripUpdated::class]);
    $draft = [storedStrip('hero', ['kop' => 'Oud'], id: 'a')];
    $page = Page::factory()->withDraft($draft)->create();

    try {
        UpdateStrip::run($page, 'a', [...heroValues(), ...$change], chooseLevels: true);
        $this->fail('The values were accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($path);
    }

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBe($draft);
    Event::assertNotDispatched(StripUpdated::class);
})->with([
    'a required field is missing' => [['kop' => null], 'kop'],
    'too few items' => [['knoppen' => []], 'knoppen'],
    'too many items' => [['knoppen' => array_fill(0, 3, ['tekst' => 'Ga', 'gaat_naar' => ['url' => '/']])], 'knoppen'],
    'a choice outside the options' => [['knoppen' => [['tekst' => 'Ga', 'gaat_naar' => ['url' => '/'], 'stijl' => 'groot']]], 'knoppen.0.stijl'],
    'a page that does not exist' => [['knoppen' => [['tekst' => 'Ga', 'gaat_naar' => ['page' => 999]]]], 'knoppen.0.gaat_naar'],
]);

it('labels the fields in the validation messages', function (): void {
    $page = Page::factory()->withDraft([storedStrip('hero', id: 'a')])->create();

    try {
        UpdateStrip::run($page, 'a', [...heroValues(), 'kop' => null], chooseLevels: true);
        $this->fail('The values were accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['kop'][0] ?? '')->toContain('Kop');
    }
});

it('refuses with the messages of the CMS, in its language', function (string $locale, string $message): void {
    app()->setLocale($locale);
    $page = Page::factory()->withDraft([storedStrip('hero', id: 'a')])->create();

    try {
        UpdateStrip::run($page, 'a', [...heroValues(), 'kop' => null], chooseLevels: true);
        $this->fail('The values were accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['kop'][0] ?? '')->toBe($message);
    }
})->with([
    'Dutch' => ['nl', 'Vul Kop in.'],
    'English' => ['en', 'Fill in Kop.'],
]);

it('refuses a strip that is not on the page', function (): void {
    Event::fake([StripUpdated::class]);
    $page = Page::factory()->withDraft([storedStrip('hero', id: 'a')])->create();

    expect(fn (): mixed => UpdateStrip::run($page, 'x', heroValues(), chooseLevels: true))->toThrow(StripNotFound::class);

    Event::assertNotDispatched(StripUpdated::class);
});

it('refuses to change a strip of a type that no longer exists and keeps its data', function (): void {
    Event::fake([StripUpdated::class]);
    $draft = [storedStrip('oud', ['x' => 1], id: 'old')];
    $page = Page::factory()->withDraft($draft)->create();

    expect(fn (): mixed => UpdateStrip::run($page, 'old', ['x' => 2], chooseLevels: true))->toThrow(UnknownStripType::class);

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBe($draft);
    Event::assertNotDispatched(StripUpdated::class);
});

it('stores a heading that was stored as text as a text with its level when another field changes', function (): void {
    app()->instance(StripTypes::class, new StripTypes([new Banner]));
    $card = (string) Str::uuid();
    $page = Page::factory()->published()->withStrips([
        storedStrip('banner', ['kop' => 'Welkom', 'kaarten' => [['id' => $card, 'titel' => 'Spelen']]], id: 'a'),
    ])->create();

    $strip = UpdateStrip::run($page, 'a', ['label' => 'Nieuw'], chooseLevels: true);

    expect($strip->values['kop'])->toBe(['text' => 'Welkom', 'tag' => 'h2'])
        ->and($strip->values['kaarten'])->toBe([['id' => $card, 'titel' => ['text' => 'Spelen', 'tag' => 'h3']]]);
});

it('refuses a level its heading does not offer', function (array $values, string $path): void {
    app()->instance(StripTypes::class, new StripTypes([new Banner]));
    $draft = [storedStrip('banner', ['kop' => ['text' => 'Welkom', 'tag' => 'h2']], id: 'a')];
    $page = Page::factory()->withDraft($draft)->create();

    try {
        UpdateStrip::run($page, 'a', $values, chooseLevels: true);
        $this->fail('The values were accepted.');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toBe([$path]);
    }

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBe($draft);
})->with([
    'its own heading' => [['kop' => ['text' => 'Welkom', 'tag' => 'h4']], 'kop.tag'],
    'a heading in a card' => [['kaarten' => [['titel' => ['text' => 'Spelen', 'tag' => 'h1']]]], 'kaarten.0.titel.tag'],
]);

/**
 * A published banner with a heading of its own at H3 and a card whose title is at H2: neither is the default level.
 *
 * @return array{0: Page, 1: string}
 */
function bannerAtOtherLevels(): array
{
    app()->instance(StripTypes::class, new StripTypes([new Banner]));
    $card = (string) Str::uuid();
    $page = Page::factory()->published()->withStrips([
        storedStrip('banner', ['kop' => ['text' => 'Welkom', 'tag' => 'h3'], 'kaarten' => [['id' => $card, 'titel' => ['text' => 'Spelen', 'tag' => 'h2']]]], id: 'a'),
    ])->create();

    return [$page, $card];
}

it('keeps the stored level of every heading for an editor who does not choose levels, also in a list', function (): void {
    [$page, $card] = bannerAtOtherLevels();

    $strip = UpdateStrip::run($page, 'a', [
        'kop' => ['text' => 'Welkom bij de club', 'tag' => 'h1'],
        'kaarten' => [['id' => $card, 'titel' => ['text' => 'Spelen en trainen', 'tag' => 'h3']], ['titel' => ['text' => 'Nieuw', 'tag' => 'h2']]],
    ], chooseLevels: false);

    expect($strip->values['kop'])->toBe(['text' => 'Welkom bij de club', 'tag' => 'h3'])
        ->and($strip->values['kaarten'][0])->toBe(['id' => $card, 'titel' => ['text' => 'Spelen en trainen', 'tag' => 'h2']])
        ->and($strip->values['kaarten'][1]['titel'])->toBe(['text' => 'Nieuw', 'tag' => 'h3'])
        ->and(Page::query()->findOrFail($page->id)->draft_strips[0]['values']['kop'] ?? null)->toBe(['text' => 'Welkom bij de club', 'tag' => 'h3']);
});

it('keeps a level that is not the default level when an editor who does not choose levels empties a heading', function (): void {
    [$page, $card] = bannerAtOtherLevels();

    $strip = UpdateStrip::run($page, 'a', ['kaarten' => [['id' => $card, 'titel' => null]]], chooseLevels: false);

    expect($strip->values['kaarten'][0]['titel'])->toBe(['text' => null, 'tag' => 'h2']);
});

it('ignores a level the heading does not offer from an editor who does not choose levels', function (): void {
    [$page] = bannerAtOtherLevels();

    $strip = UpdateStrip::run($page, 'a', ['kop' => ['text' => 'Welkom', 'tag' => 'h6']], chooseLevels: false);

    expect($strip->values['kop'])->toBe(['text' => 'Welkom', 'tag' => 'h3']);
});

it('changes the level of a heading for an editor who chooses levels, also in a list', function (): void {
    [$page, $card] = bannerAtOtherLevels();

    $strip = UpdateStrip::run($page, 'a', [
        'kop' => ['text' => 'Welkom', 'tag' => 'h1'],
        'kaarten' => [['id' => $card, 'titel' => ['text' => 'Spelen', 'tag' => 'h3']]],
    ], chooseLevels: true);

    expect($strip->values['kop'])->toBe(['text' => 'Welkom', 'tag' => 'h1'])
        ->and($strip->values['kaarten'][0]['titel'])->toBe(['text' => 'Spelen', 'tag' => 'h3']);
});

it('stores an image as the id of its file', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->withDraft([storedStrip('hero', storedHeroValues(), id: 'a')])->create();

    $strip = UpdateStrip::run($page, 'a', ['foto' => (string) $media->id], chooseLevels: true);

    expect($strip->values['foto'])->toBe($media->id)
        ->and(Page::query()->findOrFail($page->id)->draft_strips[0]['values']['foto'] ?? null)->toBe($media->id);
});

it('refuses an image whose file is gone, also when another field changes', function (): void {
    $media = Media::factory()->create();
    $draft = [storedStrip('hero', [...storedHeroValues(), 'foto' => $media->id], id: 'a')];
    $page = Page::factory()->withDraft($draft)->create();
    $media->delete();

    try {
        UpdateStrip::run($page, 'a', ['intro' => 'Sinds 1962.'], chooseLevels: true);
        $this->fail('The values were accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['foto' => ['De gekozen afbeelding bij Foto bestaat niet meer.']]);
    }

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBe($draft);
});
