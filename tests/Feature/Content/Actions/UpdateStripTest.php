<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Content\Actions\UpdateStrip;
use Made\CmsPackage\Content\Events\StripUpdated;
use Made\CmsPackage\Content\Exceptions\StripNotFound;
use Made\CmsPackage\Content\Exceptions\UnknownStripType;
use Made\CmsPackage\Content\Models\Page;

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
    ]);

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
        ])
        ->and($stored->draft_strips)->toBe([$strip->toArray()])
        ->and($stored->strips)->toBe($published);
});

it('keeps the id, type and visibility of the strip', function (): void {
    $page = Page::factory()->withDraft([storedStrip('hero', visible: false, id: 'a')])->create();

    $strip = UpdateStrip::run($page, 'a', heroValues());

    expect($strip->id)->toBe('a')
        ->and($strip->type)->toBe('hero')
        ->and($strip->visible)->toBeFalse();
});

it('dispatches an event for the updated strip', function (): void {
    Event::fake([StripUpdated::class]);
    $page = Page::factory()->withDraft([storedStrip('hero', id: 'a')])->create();

    UpdateStrip::run($page, 'a', heroValues());

    Event::assertDispatched(StripUpdated::class, fn (StripUpdated $event): bool => $event->page->is($page)
        && $event->strip->id === 'a'
        && $event->strip->values['kop'] === 'TTV Merwestad');
});

it('refuses values that break the definition', function (array $change, string $path): void {
    Event::fake([StripUpdated::class]);
    $draft = [storedStrip('hero', ['kop' => 'Oud'], id: 'a')];
    $page = Page::factory()->withDraft($draft)->create();

    try {
        UpdateStrip::run($page, 'a', [...heroValues(), ...$change]);
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
        UpdateStrip::run($page, 'a', [...heroValues(), 'kop' => null]);
        $this->fail('The values were accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['kop'][0] ?? '')->toContain('Kop');
    }
});

it('refuses a strip that is not on the page', function (): void {
    Event::fake([StripUpdated::class]);
    $page = Page::factory()->withDraft([storedStrip('hero', id: 'a')])->create();

    expect(fn (): mixed => UpdateStrip::run($page, 'x', heroValues()))->toThrow(StripNotFound::class);

    Event::assertNotDispatched(StripUpdated::class);
});

it('refuses to change a strip of a type that no longer exists and keeps its data', function (): void {
    Event::fake([StripUpdated::class]);
    $draft = [storedStrip('oud', ['x' => 1], id: 'old')];
    $page = Page::factory()->withDraft($draft)->create();

    expect(fn (): mixed => UpdateStrip::run($page, 'old', ['x' => 2]))->toThrow(UnknownStripType::class);

    expect(Page::query()->findOrFail($page->id)->draft_strips)->toBe($draft);
    Event::assertNotDispatched(StripUpdated::class);
});
