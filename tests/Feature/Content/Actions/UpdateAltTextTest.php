<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Content\Actions\UpdateAltText;
use Made\CmsPackage\Content\Events\AltTextUpdated;
use Made\CmsPackage\Content\Models\Media;

it('stores the alt text of a file, trimmed', function (): void {
    $media = Media::factory()->create();

    UpdateAltText::run($media, '  Het eerste team na de kampioenswedstrijd ');

    expect(Media::query()->findOrFail($media->id)->alt)->toBe('Het eerste team na de kampioenswedstrijd');
});

it('takes away the alt text for a blank one', function (?string $alt): void {
    $media = Media::factory()->withAlt('Het eerste team')->create();

    UpdateAltText::run($media, $alt);

    expect(Media::query()->findOrFail($media->id)->alt)->toBeNull()
        ->and(Media::query()->withoutAlt()->pluck('id')->all())->toBe([$media->id]);
})->with(['none' => [null], 'blank' => ['   ']]);

it('stores the description of the download link of a document in the same field', function (): void {
    $media = Media::factory()->document()->create();

    UpdateAltText::run($media, 'Wedstrijdschema najaar 2026 (pdf)');

    expect(Media::query()->findOrFail($media->id)->alt)->toBe('Wedstrijdschema najaar 2026 (pdf)');
});

it('takes an alt text of 255 characters and refuses a longer one', function (): void {
    Event::fake([AltTextUpdated::class]);
    $media = Media::factory()->withAlt('Het eerste team')->create();

    UpdateAltText::run($media, str_repeat('é', 255));

    try {
        UpdateAltText::run($media, str_repeat('é', 256));
        $this->fail('The alt text was stored.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['alt' => ['Een alt-tekst mag hoogstens 255 tekens zijn.']]);
    }

    expect(Media::query()->findOrFail($media->id)->alt)->toBe(str_repeat('é', 255));
    Event::assertDispatchedTimes(AltTextUpdated::class, 1);
});

it('dispatches an event when the alt text changes, and only then', function (): void {
    Event::fake([AltTextUpdated::class]);
    $media = Media::factory()->withAlt('Het eerste team')->create();

    UpdateAltText::run($media, ' Het eerste team ');
    Event::assertNotDispatched(AltTextUpdated::class);

    UpdateAltText::run($media, 'Het tweede team');
    Event::assertDispatched(AltTextUpdated::class, fn (AltTextUpdated $event): bool => $event->media->is($media) && $event->media->alt === 'Het tweede team');
});
