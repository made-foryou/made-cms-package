<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\SyncMediaUsage;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost']);
    Storage::fake('public');

    // The fake media disk has no address of its own: this serves its files. It goes before the site, whose pages take
    // every address that is left.
    Route::get('storage/{path}', fn (string $path) => response()->file(Storage::disk('public')->path($path)))->where('path', '.*');
    useWorkbenchSite();

    $this->teams = MediaFolder::factory()->create(['name' => 'Teams']);
    $this->photo = UploadMedia::run(new UploadedMedia(quadrants(2400, 1600), 'Teamfoto Merwestad.jpg'));
    $this->page = Page::factory()->published()->at('/teams')->withStrips([
        storedStrip('hero', ['kop' => 'Onze teams'], id: 'hero'),
        storedStrip('cards', ['kop' => 'Wat we doen'], id: 'cards'),
    ])->create(['title' => 'Teams']);

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * A JavaScript expression for one slot of the crop step.
 */
function cropSlot(string $slot): string
{
    return "document.querySelector('[data-slot=\"crop-{$slot}\"]')";
}

/**
 * A JavaScript condition: the crop step is open with its photo on the stage.
 */
function cropReady(): string
{
    return "document.querySelector('[data-media-crop]').open && ! ".cropSlot('picture').'.hidden && ! '.cropSlot('save').'.disabled';
}

/**
 * The edit shell with the crop step opened for these files, as the media element and the upload field open it. The
 * files it stores are in window.changed, what it answers when it closes in window.answered.
 *
 * @param  list<Media>  $files
 */
function openCrop(Page $page, array $files, bool $upload = false): mixed
{
    $shell = visit('/made-cms/pages/'.$page->id.'/edit');

    expect($shell->script(waitUntil("document.querySelector('.bewerkframe').contentDocument?.querySelector('made-cms-outlines')?.shadowRoot?.querySelectorAll('.omlijning').length === 2")))->toBeTrue();

    $files = json_encode(array_map(static fn (Media $media): array => ['id' => $media->id, 'name' => $media->name, 'croppable' => true], $files), JSON_THROW_ON_ERROR);
    $upload = $upload ? 'true' : 'false';

    $shell->script(<<<JS
        () => {
            window.changed = [];
            window.answered = null;
            document.addEventListener('made-cms:media-changed', (event) => window.changed.push(event.detail.file));
            document.querySelector('[data-media-crop]').dispatchEvent(new CustomEvent('made-cms:crop', { detail: {
                files: {$files},
                upload: {$upload},
                opener: document.querySelector('[data-slot="structure-add"]'),
                done: (results) => { window.answered = results.map((result) => [result.file.id, result.handled]); },
            } }));
        }
        JS);

    expect($shell->script(waitUntil(cropReady())))->toBeTrue();

    return $shell;
}

/**
 * A JavaScript condition: the crop step closed and told its host.
 */
function cropClosed(): string
{
    return "! document.querySelector('[data-media-crop]').open && window.answered !== null";
}

it('shows the whole original on its stage, with what the photo becomes', function (): void {
    $shell = openCrop($this->page, [$this->photo]);
    $date = $this->photo->created_at?->locale('nl')->isoFormat('D MMMM YYYY');

    $shell->assertSeeIn('[data-slot="crop-eyebrow"]', 'Media / bijsnijden')
        ->assertSeeIn('#made-cms-bijsnijden-kop', 'Maak de foto klaar voor de site')
        ->assertSeeIn('[data-slot="crop-area"]', 'Uitsnede origineel · 2400 × 1600')
        ->assertAttribute('[data-ratio="original"]', 'aria-pressed', 'true')
        ->assertAttribute('[data-ratio="16:9"]', 'aria-pressed', 'false')
        ->assertAttribute('[data-slot="crop-flip"]', 'aria-pressed', 'false')
        ->assertSeeIn('[data-slot="crop-save"]', 'Opslaan')
        ->assertValue('[data-slot="crop-name"]', 'teamfoto-merwestad')
        ->assertValue('[data-slot="crop-alt"]', '')
        ->assertValue('[data-slot="crop-folder"]', '');

    expect($shell->script('() => '.cropSlot('line').'.textContent'))->toStartWith('teamfoto-merwestad.jpg · 2400 × 1600 · ')
        ->and($shell->script('() => '.cropSlot('line').'.textContent'))->toEndWith(' · geüpload '.$date)
        ->and($shell->script('() => '.cropSlot('used').'.hidden'))->toBeTrue()
        ->and($shell->script('() => '.cropSlot('steps-row').'.hidden'))->toBeTrue()
        ->and($shell->script('() => '.cropSlot('weight-from').'.textContent'))->toMatch('/^\d+ kB jpg$/')
        ->and($shell->script('() => '.cropSlot('weight-to').'.textContent'))->toMatch('/^± \d+ kB WebP$/')
        ->and($shell->script("() => [...document.querySelectorAll('[data-slot=\"crop-folder\"] option')].map((option) => option.textContent)"))->toBe(['Alle bestanden', 'Teams'])
        ->and($shell->script('() => document.activeElement?.dataset.slot'))->toBe('crop-frame')
        // The canvas shows the original: red at the top left, yellow at the bottom right.
        ->and($shell->script('() => { const canvas = '.cropSlot('canvas').'; const pixel = (x, y) => [...canvas.getContext("2d").getImageData(x, y, 1, 1).data].slice(0, 3); return [pixel(10, 10), pixel(canvas.width - 10, canvas.height - 10)]; }'))
        ->sequence(
            fn ($colour) => $colour->toEqualWithDelta([200, 40, 40], 40),
            fn ($colour) => $colour->toEqualWithDelta([230, 200, 40], 40),
        );

    $shell->screenshot(filename: 'crop-step-light')->assertNoJavaScriptErrors();
});

it('crops the photo to 16:9 and tells the host', function (): void {
    $shell = openCrop($this->page, [$this->photo]);

    $shell->click('[data-ratio="16:9"]')
        ->assertAttribute('[data-ratio="16:9"]', 'aria-pressed', 'true')
        ->assertSeeIn('[data-slot="crop-area"]', 'Uitsnede 16:9 · 2400 × 1350');

    expect($shell->script('() => '.cropSlot('weight-to').'.textContent'))->toMatch('/^± \d+ kB WebP$/');

    $shell->click('[data-slot="crop-save"]');

    expect($shell->script(waitUntil(cropClosed())))->toBeTrue();

    $media = $this->photo->refresh();

    expect($media->crop)->toBe(['x' => 0, 'y' => 125, 'width' => 2400, 'height' => 1350, 'rotation' => 0, 'flip' => false, 'scale_down' => true])
        ->and([$media->width, $media->height])->toBe([1920, 1080])
        ->and([$media->focal_x, $media->focal_y])->toBe([0.5, 0.5])
        ->and($shell->script('() => window.answered'))->toBe([[$media->id, true]])
        ->and($shell->script('() => window.changed.map((file) => file.meta.split(" · ")[0])'))->toBe(['1920 × 1080'])
        ->and($shell->script('() => document.activeElement?.dataset.slot'))->toBe('structure-add');

    $shell->assertNoJavaScriptErrors();
});

it('moves the frame and the focal point with the arrow keys', function (): void {
    $shell = openCrop($this->page, [$this->photo]);

    $shell->click('[data-ratio="16:9"]')
        ->keys('[data-slot="crop-frame"]', ['ArrowDown', 'ArrowDown'])
        ->assertSeeIn('[data-slot="crop-area"]', 'Uitsnede 16:9 · 2400 × 1350')
        // One step of 1% of the frame to the right, one of 10% up: from the middle of the original to 1224 × 665.
        ->keys('[data-slot="crop-focal"]', ['ArrowRight', 'Shift+ArrowUp']);

    expect($shell->script(waitUntil(cropSlot('announce').".textContent === 'Brandpunt 51% van links, 38% van boven'")))->toBeTrue();

    $shell->click('[data-slot="crop-save"]');

    expect($shell->script(waitUntil(cropClosed())))->toBeTrue();

    $media = $this->photo->refresh();

    // Two steps of 1% of the original (16 px) down from 125; the focal point (665 − 157) / 1350 from the top.
    expect($media->crop['y'] ?? null)->toBe(157)
        ->and($media->focal_x)->toBe(0.51)
        ->and($media->focal_y)->toBe(0.3763);
});

it('turns and mirrors the photo and pulls a corner', function (): void {
    $shell = openCrop($this->page, [$this->photo]);

    $shell->click('[data-slot="crop-right"]')
        ->assertSeeIn('[data-slot="crop-area"]', 'Uitsnede origineel · 1600 × 2400')
        ->click('[data-slot="crop-flip"]')
        ->assertAttribute('[data-slot="crop-flip"]', 'aria-pressed', 'true')
        ->click('[data-ratio="free"]')
        ->keys('[data-corner="se"]', ['Shift+ArrowLeft', 'Shift+ArrowUp'])
        ->assertSeeIn('[data-slot="crop-area"]', 'Uitsnede vrij · 1440 × 2160')
        ->click('[data-slot="crop-save"]');

    expect($shell->script(waitUntil(cropClosed())))->toBeTrue();

    $media = $this->photo->refresh();

    expect($media->crop)->toBe(['x' => 0, 'y' => 0, 'width' => 1440, 'height' => 2160, 'rotation' => 90, 'flip' => true, 'scale_down' => true])
        ->and([$media->width, $media->height])->toBe([1440, 2160]);
});

it('goes back to the whole original with Herstel', function (): void {
    $shell = openCrop($this->page, [$this->photo]);

    $shell->click('[data-slot="crop-left"]')
        ->click('[data-ratio="1:1"]')
        ->assertSeeIn('[data-slot="crop-area"]', 'Uitsnede 1:1 · 1600 × 1600')
        ->click('[data-slot="crop-reset"]')
        ->assertSeeIn('[data-slot="crop-area"]', 'Uitsnede origineel · 2400 × 1600')
        ->assertAttribute('[data-ratio="original"]', 'aria-pressed', 'true')
        ->click('[data-slot="crop-save"]');

    expect($shell->script(waitUntil(cropClosed())))->toBeTrue()
        ->and($this->photo->refresh()->crop)->toBeNull()
        ->and($shell->script('() => window.changed'))->toBe([]);
});

it('keeps the photo at its size when it is not scaled down', function (): void {
    $shell = openCrop($this->page, [$this->photo]);
    $before = $shell->script('() => '.cropSlot('weight-to').'.textContent');

    $shell->click('.bijsnijden-verklein .schakelrij');

    expect($shell->script('() => '.cropSlot('weight-to').'.textContent'))->not->toBe($before);

    $shell->click('[data-slot="crop-save"]');

    expect($shell->script(waitUntil(cropClosed())))->toBeTrue();

    $media = $this->photo->refresh();

    expect([$media->width, $media->height])->toBe([2400, 1600])
        ->and($media->crop['scale_down'] ?? null)->toBeFalse();
});

it('stores the name, the alt text and the folder and leaves the photo with "Zo laten, zonder snijden"', function (): void {
    $shell = openCrop($this->page, [$this->photo]);

    $shell->click('[data-ratio="1:1"]')
        ->type('[data-slot="crop-name"]', 'Clubavond Oktober')
        ->type('[data-slot="crop-alt"]', 'Leden aan de bar na de clubavond')
        ->select('[data-slot="crop-folder"]', (string) $this->teams->id)
        ->click('[data-slot="crop-keep"]');

    expect($shell->script(waitUntil(cropClosed())))->toBeTrue();

    $media = $this->photo->refresh();

    expect($media->name)->toBe('clubavond-oktober')
        ->and($media->alt)->toBe('Leden aan de bar na de clubavond')
        ->and($media->folder_id)->toBe($this->teams->id)
        ->and($media->crop)->toBeNull()
        ->and($shell->script('() => window.changed.at(-1).name'))->toBe('clubavond-oktober')
        ->and($shell->script('() => window.answered'))->toBe([[$media->id, true]]);
});

it('keeps a refused name at its field and stays open', function (): void {
    $shell = openCrop($this->page, [$this->photo]);

    $shell->type('[data-slot="crop-name"]', '??')
        ->click('[data-slot="crop-save"]');

    expect($shell->script(waitUntil('! '.cropSlot('name-error').'.hidden')))->toBeTrue();

    $shell->assertSeeIn('[data-slot="crop-name-error"]', 'Geef het bestand een naam met letters of cijfers.')
        ->assertAttribute('[data-slot="crop-name"]', 'aria-invalid', '');

    expect($shell->script("() => document.querySelector('[data-media-crop]').open"))->toBeTrue()
        ->and($shell->script('() => document.activeElement?.dataset.slot'))->toBe('crop-name')
        ->and($this->photo->refresh()->name)->toBe('teamfoto-merwestad');
});

it('stores a typed alt text when Escape closes it, and leaves the photo', function (): void {
    $shell = openCrop($this->page, [$this->photo]);

    $shell->click('[data-ratio="1:1"]')
        ->type('[data-slot="crop-alt"]', 'Het eerste team')
        ->keys('[data-slot="crop-alt"]', 'Escape');

    expect($shell->script(waitUntil(cropClosed())))->toBeTrue();

    $media = $this->photo->refresh();

    expect($media->alt)->toBe('Het eerste team')
        ->and($media->crop)->toBeNull();
});

it('takes the photos of an upload one after the other', function (): void {
    $second = UploadMedia::run(new UploadedMedia(quadrants(1200, 800), 'Clubavond.jpg'));
    $shell = openCrop($this->page, [$this->photo, $second], upload: true);

    $shell->assertSeeIn('[data-slot="crop-eyebrow"]', 'Uploaden / bestand 1 van 2')
        ->assertSeeIn('[data-slot="crop-step"]', 'Bestand 1 van 2 · daarna clubavond')
        ->assertSeeIn('[data-slot="crop-save"]', 'Opslaan en volgende');

    expect($shell->script("() => [...document.querySelectorAll('[data-slot=\"crop-steps\"] .stap')].map((bar) => bar.className)"))->toBe(['stap actief', 'stap']);

    $shell->click('[data-ratio="1:1"]')->click('[data-slot="crop-save"]');

    expect($shell->script(waitUntil(cropReady().' && '.cropSlot('step').".textContent === 'Bestand 2 van 2'")))->toBeTrue();

    $shell->assertSeeIn('[data-slot="crop-eyebrow"]', 'Uploaden / bestand 2 van 2')
        ->assertSeeIn('[data-slot="crop-save"]', 'Opslaan')
        ->assertValue('[data-slot="crop-name"]', 'clubavond')
        ->assertSeeIn('[data-slot="crop-area"]', 'Uitsnede origineel · 1200 × 800')
        ->keys('[data-slot="crop-frame"]', 'Escape');

    expect($shell->script(waitUntil(cropClosed())))->toBeTrue()
        ->and($shell->script('() => window.answered'))->toBe([[$this->photo->id, true], [$second->id, true]])
        ->and($this->photo->refresh()->crop['width'] ?? null)->toBe(1600)
        ->and($second->refresh()->crop)->toBeNull();
});

it('leaves the folder out for an editor who does not open the media library', function (): void {
    $this->actingAs(Editor::factory()->withRoles(Role::Owner)->create(), 'made-cms');

    $shell = openCrop($this->page, [$this->photo]);

    expect($shell->script("() => document.querySelector('[data-slot=\"crop-folder\"]')"))->toBeNull();

    $shell->click('[data-ratio="4:3"]')->click('[data-slot="crop-save"]');

    expect($shell->script(waitUntil(cropClosed())))->toBeTrue()
        ->and($this->photo->refresh()->crop['width'] ?? null)->toBe(2133);
});

it('says on how many pages the photo changes', function (): void {
    $this->page->update(['strips' => [
        storedStrip('hero', ['kop' => 'Onze teams', 'foto' => $this->photo->id], id: 'hero'),
        storedStrip('cards', ['kop' => 'Wat we doen'], id: 'cards'),
    ]]);
    SyncMediaUsage::run($this->page);

    $shell = openCrop($this->page, [$this->photo]);

    $shell->assertSeeIn('[data-slot="crop-used"]', 'Staat op 1 pagina. Bijsnijden verandert hem daar meteen, ook op de gepubliceerde site.');
});

it('follows the dark theme of the computer', function (): void {
    $shell = visit('/made-cms/pages/'.$this->page->id.'/edit')->inDarkMode();

    expect($shell->script(waitUntil("document.querySelector('.bewerkframe').contentDocument?.querySelector('made-cms-outlines')?.shadowRoot?.querySelectorAll('.omlijning').length === 2")))->toBeTrue();

    $shell->script("() => document.querySelector('[data-media-crop]').dispatchEvent(new CustomEvent('made-cms:crop', { detail: { files: [{ id: {$this->photo->id}, croppable: true }] } }))");

    expect($shell->script(waitUntil(cropReady())))->toBeTrue()
        ->and($shell->script("() => [getComputedStyle(document.querySelector('[data-media-crop]')).backgroundColor, getComputedStyle(".cropSlot('stage').').backgroundColor]'))
        ->toBe(['rgb(14, 43, 38)', 'rgb(10, 33, 28)']);

    $shell->screenshot(filename: 'crop-step-dark');
});
