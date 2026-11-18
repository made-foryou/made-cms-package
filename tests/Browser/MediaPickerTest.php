<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\ListMedia;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;

const KAART_KIEZEN = '7d0c2b9e-5a41-4f3e-8b6d-2e9f0a1c3b4d';

beforeEach(function (): void {
    config(['app.url' => 'http://localhost']);
    Storage::fake('public');

    // The fake media disk has no address of its own: this serves its files. It goes before the site, whose pages take
    // every address that is left.
    Route::get('storage/{path}', fn (string $path) => Storage::disk('public')->exists($path)
        ? response()->file(Storage::disk('public')->path($path))
        : abort(404))->where('path', '.*');
    useWorkbenchSite();

    $this->teams = MediaFolder::factory()->create(['name' => 'Teams']);
    $this->nieuws = MediaFolder::factory()->create(['name' => 'Nieuws']);
    $this->teamfoto = Media::factory()->in($this->teams)->withAlt('Het eerste team voor de tafels')->create([
        'name' => 'teamfoto-merwestad',
        'path' => 'media/01k9/teamfoto-merwestad.webp',
        'thumb_path' => 'media/01k9/thumb-klein.webp',
        'width' => 1600,
        'height' => 1067,
        'size' => 412_000,
        'created_at' => now()->subDays(2),
    ]);
    $this->training = Media::factory()->in($this->teams)->create(['name' => 'training-dinsdag', 'created_at' => now()->subDay()]);
    $this->clubavond = Media::factory()->in($this->nieuws)->create(['name' => 'clubavond', 'created_at' => now()]);
    $this->schema = Media::factory()->in($this->teams)->document()->create(['name' => 'wedstrijdschema']);

    // The photos and their thumbnails are on the disk, so their tiles show them.
    foreach ([$this->teamfoto, $this->training, $this->clubavond] as $media) {
        Storage::disk('public')->put($media->path, (string) file_get_contents(photo(1600, 1067, 'webp')));
        Storage::disk('public')->put((string) $media->thumb_path, (string) file_get_contents(photo(540, 360, 'webp')));
    }

    $this->page = Page::factory()->published()->at('/teams')->withStrips([
        storedStrip('hero', ['kop' => 'Onze teams'], id: 'hero'),
        storedStrip('cards', ['kop' => 'Wat we doen', 'kaarten' => [['id' => KAART_KIEZEN, 'titel' => 'Trainen']]], id: 'cards'),
    ])->create(['title' => 'Teams']);

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * A JavaScript condition: the picker is open and shows these files, in this order.
 *
 * @param  list<string>  $names
 */
function pickerShows(array $names): string
{
    $quoted = json_encode($names, JSON_THROW_ON_ERROR);

    return "document.querySelector('[data-media-picker]').open && ! document.querySelector('[data-media]').hasAttribute('data-loading') && JSON.stringify([...document.querySelectorAll('[data-media-picker] .tegel [data-slot=\"media-tile-name\"]')].map((name) => name.textContent)) === JSON.stringify({$quoted})";
}

/**
 * A script for $page->script(): every folder of the picker with its number and whether it is open.
 */
function pickerFolders(): string
{
    return <<<'JS'
        () => [...document.querySelectorAll('[data-media-picker] .kmap')].map((folder) => [
            folder.querySelector('.kmap-naam').textContent.trim(),
            folder.querySelector('.kmap-aantal').textContent,
            folder.getAttribute('aria-pressed'),
        ])
        JS;
}

/**
 * The selector of the tile of a file in the picker.
 */
function pickerTile(Media $media): string
{
    return '[data-media-picker] .tegel[data-id="'.$media->id.'"]';
}

/**
 * A JavaScript condition: the media element of a field shows a photo, none, or a file that left Media.
 */
function pickedState(string $path, string $state): string
{
    return "document.querySelector('[data-path=\"{$path}\"] [data-slot=\"image-card\"]')?.dataset.state === '{$state}'";
}

it('loads the thumbnails in the tiles and the foot, never the photos the site shows', function (): void {
    $shell = openStrip($this->page, 'hero', pickedState('foto', 'empty'));
    $shell->click('[data-path="foto"] [data-slot="image-pick"]');

    $tiles = "[...document.querySelectorAll('[data-media-picker] .tegel [data-slot=\"media-tile-thumb\"]')]";

    expect($shell->script(waitUntil(pickerShows(['clubavond', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue()
        ->and($shell->script(waitUntil("{$tiles}.every((thumb) => thumb.complete && thumb.naturalWidth === 540)")))->toBeTrue()
        ->and($shell->script("() => {$tiles}.map((thumb) => new URL(thumb.src).pathname)"))->toBe(array_map(
            static fn (Media $media): string => '/storage/'.$media->thumb_path,
            [$this->clubavond, $this->training, $this->teamfoto],
        ));

    $shell->click(pickerTile($this->teamfoto));

    expect($shell->script(waitUntil("document.querySelector('[data-slot=\"media-picker-thumb\"]').naturalWidth === 540")))->toBeTrue()
        ->and($shell->script("() => new URL(document.querySelector('[data-slot=\"media-picker-thumb\"]').src).pathname"))->toBe('/storage/media/01k9/thumb-klein.webp');
});

it('shows the folders of the library without managing them: no field, no dragging, and outside a list no selecting', function (): void {
    $shell = openStrip($this->page, 'hero', pickedState('foto', 'empty'));
    $shell->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil(pickerShows(['clubavond', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue()
        ->and($shell->script(pickerFolders()))->not->toBeEmpty()
        ->and($shell->script("() => document.querySelectorAll('[data-media-picker] [data-slot=\"media-folder-field\"], [data-media-picker] [data-slot=\"media-select\"], [data-media-picker] [data-slot=\"media-rename\"]').length"))->toBe(0)
        ->and($shell->script("() => document.querySelectorAll('[data-media-picker] .tegel[draggable=\"true\"]').length"))->toBe(0);

    $shell->assertNoJavaScriptErrors();
});

it('opens from the media element as a modal dialog with the images of the library, newest first', function (): void {
    $shell = openStrip($this->page, 'hero', pickedState('foto', 'empty'));

    $shell->assertSeeIn('[data-path="foto"] [data-slot="image-pick"]', 'Kiezen uit media')
        ->assertAttribute('[data-path="foto"] [data-slot="image-pick"]', 'aria-haspopup', 'dialog')
        ->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil(pickerShows(['clubavond', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue()
        ->and($shell->script('() => document.activeElement?.dataset.slot'))->toBe('media-search')
        ->and($shell->script(pickerFolders()))->toBe([
            ['Alle bestanden', '3', 'true'],
            ['Nieuws', '1', 'false'],
            ['Teams', '2', 'false'],
        ]);

    $shell->assertAttribute('[data-media-picker]', 'aria-modal', 'true')
        ->assertSeeIn('#made-cms-kiezer-kop', 'Kies een afbeelding')
        ->assertSeeIn('[data-slot="media-picker-for"]', 'voor Foto in Hero · Minimaal 1600 px breed.')
        ->assertSeeIn('[data-slot="media-kind"]', 'Afbeeldingen')
        ->assertSeeIn('[data-slot="media-status"]', '3 afbeeldingen')
        ->assertSeeIn('[data-slot="media-picker-name"]', 'Nog niets gekozen')
        ->assertSeeIn('[data-slot="media-picker-use"]', 'Kies eerst een afbeelding')
        ->assertAttribute('[data-slot="media-picker-use"]', 'aria-disabled', 'true')
        // The image without an alt text has its amber dot, read out by its name.
        ->assertVisible(pickerTile($this->training).' [data-slot="media-tile-no-alt"]')
        ->assertAttribute(pickerTile($this->training).' [data-slot="media-tile-no-alt"]', 'aria-label', 'Geen alt-tekst')
        ->assertMissing(pickerTile($this->teamfoto).' [data-slot="media-tile-no-alt"]')
        ->assertSeeIn(pickerTile($this->teamfoto).' [data-slot="media-tile-meta"]', '1600 × 1067 · 412 kB')
        ->assertSeeIn(pickerTile($this->teamfoto).' [data-slot="media-tile-extension"]', 'webp')
        ->assertNoJavaScriptErrors();

    // A modal dialog: the panel and the site behind it are out of reach.
    expect($shell->script("() => document.querySelector('[data-media-picker]').matches(':modal')"))->toBeTrue();
});

it('searches by the alt text and the title of a page, shows the images without one, sorts by name and opens a folder', function (): void {
    MediaUsage::factory()->for($this->clubavond)->for(Page::factory()->state(['title' => 'Clubnieuws']))->create();
    $shell = openStrip($this->page, 'hero', pickedState('foto', 'empty'));

    $shell->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil(pickerShows(['clubavond', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue();

    $shell->assertAttribute('[data-slot="media-search"]', 'placeholder', 'Zoek op naam, alt-tekst of pagina')
        ->type('[data-slot="media-search"]', 'tafels');

    expect($shell->script(waitUntil(pickerShows(['teamfoto-merwestad']))))->toBeTrue();

    $shell->type('[data-slot="media-search"]', 'clubnieuws');

    expect($shell->script(waitUntil(pickerShows(['clubavond']))))->toBeTrue();

    $shell->type('[data-slot="media-search"]', '')->click('[data-slot="media-without-alt"]');

    expect($shell->script(waitUntil(pickerShows(['clubavond', 'training-dinsdag']))))->toBeTrue();

    $shell->assertAttribute('[data-slot="media-without-alt"]', 'aria-pressed', 'true')
        ->click('[data-slot="media-without-alt"]')
        ->click('[data-slot="media-newest"]');

    expect($shell->script(waitUntil(pickerShows(['clubavond', 'teamfoto-merwestad', 'training-dinsdag']))))->toBeTrue();

    $shell->click('[data-media-picker] .kmap[data-folder="'.$this->teams->id.'"]');

    expect($shell->script(waitUntil(pickerShows(['teamfoto-merwestad', 'training-dinsdag']))))->toBeTrue()
        ->and($shell->script(pickerFolders())[2])->toBe(['Teams', '2', 'true']);

    $shell->assertNoJavaScriptErrors();
});

it('never shows a document: searching for one gives the empty text', function (): void {
    $shell = openStrip($this->page, 'hero', pickedState('foto', 'empty'));

    $shell->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil(pickerShows(['clubavond', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue();

    $shell->type('[data-slot="media-search"]', 'wedstrijdschema');

    expect($shell->script(waitUntil(pickerShows([]))))->toBeTrue();

    $shell->assertVisible('[data-slot="media-empty"]')
        ->assertSeeIn('[data-slot="media-empty"]', 'Geen afbeeldingen gevonden. Documenten en video’s kies je hier niet.')
        ->assertSeeIn('[data-slot="media-status"]', 'Geen afbeeldingen')
        ->assertMissing(pickerTile($this->schema))
        ->assertNoJavaScriptErrors();
});

it('makes the chosen photo the value of the field, saves it and shows it on the site', function (): void {
    $shell = openStrip($this->page, 'hero', pickedState('foto', 'empty'));

    $shell->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil(pickerShows(['clubavond', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue();

    $shell->click(pickerTile($this->teamfoto))
        ->assertAttribute(pickerTile($this->teamfoto), 'aria-pressed', 'true')
        ->assertAttribute(pickerTile($this->training), 'aria-pressed', 'false')
        ->assertSeeIn('[data-slot="media-picker-name"]', 'teamfoto-merwestad')
        ->assertSeeIn('[data-slot="media-picker-alt"]', 'Alt: Het eerste team voor de tafels')
        ->assertSeeIn('[data-slot="media-picker-use"]', 'Deze gebruiken')
        ->assertAttribute('[data-slot="media-picker-use"]', 'aria-disabled', 'false')
        ->click('[data-slot="media-picker-use"]');

    expect($shell->script(waitUntil(pickedState('foto', 'filled'))))->toBeTrue()
        ->and($shell->script("() => document.querySelector('[data-media-picker]').open"))->toBeFalse()
        ->and($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and(draftOf($this->page, 'hero')['values']['foto'] ?? null)->toBe($this->teamfoto->id)
        ->and($shell->script('() => document.activeElement?.dataset.slot'))->toBe('image-pick')
        ->and($shell->script(waitUntil(siteInFrame()."?.querySelector('[data-cms-field=\"foto\"]')?.getAttribute('src')?.endsWith('media/01k9/teamfoto-merwestad.webp')")))->toBeTrue();

    $shell->assertSeeIn('[data-path="foto"] [data-slot="image-name"]', 'teamfoto-merwestad')
        ->assertSeeIn('[data-path="foto"] [data-slot="image-alt"]', 'Alt: Het eerste team voor de tafels')
        // A required field that is being picked for shows no error.
        ->assertMissing('[data-path="foto"] [data-slot="error"]')
        ->assertNoJavaScriptErrors();
});

it('opens a filled field with its photo chosen', function (): void {
    $this->page->update(['strips' => [storedStrip('hero', ['kop' => 'Onze teams', 'foto' => $this->teamfoto->id], id: 'hero'), $this->page->strips[1]]]);

    $shell = openStrip($this->page, 'hero', pickedState('foto', 'filled'));

    $shell->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil(pickerShows(['clubavond', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue();

    $shell->assertAttribute(pickerTile($this->teamfoto), 'aria-pressed', 'true')
        ->assertSeeIn('[data-slot="media-picker-name"]', 'teamfoto-merwestad')
        ->assertSeeIn('[data-slot="media-picker-use"]', 'Deze gebruiken')
        ->assertNoJavaScriptErrors();
});

it('chooses a photo for an item of a list', function (): void {
    $shell = openStrip($this->page, 'cards', "document.querySelectorAll('[data-path=\"kaarten\"] li').length === 1");

    $shell->click('[data-path="kaarten"] li:nth-child(1) [data-slot="edit"]')
        ->click('[data-path="kaarten.0.icoon"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil(pickerShows(['clubavond', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue();

    $shell->assertSeeIn('[data-slot="media-picker-for"]', 'voor Icoon in Kaarten › Item 1 · Vierkant werkt het best.')
        ->click(pickerTile($this->clubavond))
        ->click('[data-slot="media-picker-use"]');

    expect($shell->script(waitUntil(pickedState('kaarten.0.icoon', 'filled'))))->toBeTrue()
        ->and($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and(draftOf($this->page, 'cards')['values']['kaarten'][0]['icoon'] ?? null)->toBe($this->clubavond->id);

    $shell->assertNoJavaScriptErrors();
});

it('closes with Escape without a choice, keeps the focus inside and gives it back', function (): void {
    $shell = openStrip($this->page, 'hero', pickedState('foto', 'empty'));

    $shell->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil(pickerShows(['clubavond', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue();

    // Tab from the last control goes round to the first, and back.
    $shell->click(pickerTile($this->training));
    $shell->script("() => document.querySelector('[data-slot=\"media-picker-use\"]').focus()");
    $shell->keys('[data-slot="media-picker-use"]', 'Tab');

    expect($shell->script('() => document.activeElement?.dataset.slot'))->toBe('media-picker-close');

    $shell->keys('[data-slot="media-picker-close"]', 'Shift+Tab');

    expect($shell->script('() => document.activeElement?.dataset.slot'))->toBe('media-picker-use');

    $shell->keys(pickerTile($this->training), 'Escape');

    expect($shell->script(waitUntil("! document.querySelector('[data-media-picker]').open")))->toBeTrue()
        ->and($shell->script(waitUntil("document.activeElement?.dataset.slot === 'image-pick'")))->toBeTrue()
        ->and($shell->script(pickedState('foto', 'empty')))->toBeTrue()
        ->and(draftOf($this->page, 'hero')['values']['foto'] ?? null)->toBeNull();

    $shell->assertNoJavaScriptErrors();
});

it('takes a photo uploaded in the picker as the choice, in the folder it remembers for the panel', function (): void {
    $shell = openStrip($this->page, 'hero', pickedState('foto', 'empty'));

    $shell->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil(pickerShows(['clubavond', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue();

    $shell->click('[data-media-picker] .kmap[data-folder="'.$this->teams->id.'"]');

    expect($shell->script(waitUntil(pickerShows(['training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue();

    $shell->assertSeeIn('[data-media-picker] [data-picker-upload="one"] [data-slot="upload-rules"]', 'komt in de map Teams')
        ->attach('[data-media-picker] [data-picker-upload="one"] [data-slot="upload-input"]', photo(1600, 1200, 'jpg', 'Kampioenen 2026.jpg'));

    expect($shell->script(waitUntil(pickerShows(['kampioenen-2026', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue();

    $upload = Media::query()->where('name', 'kampioenen-2026')->sole();

    expect($upload->folder_id)->toBe($this->teams->id);

    // The photo goes to the crop step, which asks its alt text; the field does not.
    expect($shell->script(waitUntil("document.querySelector('[data-media-crop]').open && ! document.querySelector('[data-slot=\"crop-save\"]').disabled")))->toBeTrue();

    $shell->assertSeeIn('[data-slot="crop-eyebrow"]', 'Uploaden')
        ->assertMissing('[data-media-picker] [data-picker-upload="one"] [data-slot="upload-row-alt-input"]')
        ->type('[data-slot="crop-alt"]', 'De kampioenen van 2026')
        ->click('[data-slot="crop-keep"]');

    expect($shell->script(waitUntil("! document.querySelector('[data-media-crop]').open")))->toBeTrue()
        ->and($shell->script(waitUntil("document.querySelector('[data-slot=\"media-picker-alt\"]').textContent === 'Alt: De kampioenen van 2026'")))->toBeTrue()
        ->and($shell->script(pickerUploadMoment('rest')))->toBeTrue()
        ->and($shell->script("() => document.querySelector('".pickerTile($upload)."') !== null"))->toBeTrue()
        ->and($shell->script(pickerFolders())[2])->toBe(['Teams', '3', 'true']);

    $shell->click('[data-slot="media-picker-use"]');

    expect($shell->script(waitUntil(pickedState('foto', 'filled'))))->toBeTrue()
        ->and($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and(draftOf($this->page, 'hero')['values']['foto'] ?? null)->toBe($upload->id);

    // "Uploaden" in the panel now puts a photo in the folder the editor chose, also after the shell reloads.
    $shell->click('[data-path="foto"] [data-slot="image-upload"]')
        ->assertSeeIn('[data-path="foto"] [data-slot="upload-rules"]', 'komt in de map Teams');

    $again = openStrip($this->page, 'hero', pickedState('foto', 'filled'));

    $again->click('[data-path="foto"] [data-slot="image-upload"]')
        ->assertSeeIn('[data-path="foto"] [data-slot="upload-rules"]', 'komt in de map Teams')
        ->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($again->script(waitUntil(pickerShows(['kampioenen-2026', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue()
        ->and($again->script(pickerFolders())[2])->toBe(['Teams', '3', 'true']);

    $again->assertNoJavaScriptErrors();
});

/**
 * A JavaScript condition: the upload field of the picker is in this moment (rest, busy, done or error).
 */
function pickerUploadMoment(string $moment): string
{
    return "document.querySelector('[data-media-picker] [data-picker-upload=one] [data-upload]').dataset.moment === '{$moment}'";
}

/**
 * Opens the picker for the photo of the hero and uploads an svg in it, up to the moment the field asks its alt text. A
 * photo would go to the crop step, which asks it there; an svg has no original to crop.
 */
function uploadInPicker(mixed $shell, string $name, ?string $file = null): void
{
    $shell->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil(pickerShows(['clubavond', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue();

    $shell->attach('[data-media-picker] [data-picker-upload="one"] [data-slot="upload-input"]', $file ?? mediaFixture('logo.svg', $name));

    expect($shell->script(waitUntil(pickerUploadMoment('done'))))->toBeTrue();
}

it('crops a photo uploaded in the picker to 16:9 in the crop step, and the media element shows its new size', function (): void {
    $shell = openStrip($this->page, 'hero', pickedState('foto', 'empty'));

    $shell->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil(pickerShows(['clubavond', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue();

    $shell->attach('[data-media-picker] [data-picker-upload="one"] [data-slot="upload-input"]', quadrants(2400, 1600, 'jpg', 'Kampioenen 2026.jpg'));

    expect($shell->script(waitUntil("document.querySelector('[data-media-crop]').open && ! document.querySelector('[data-slot=\"crop-save\"]').disabled")))->toBeTrue();

    $shell->assertSeeIn('[data-slot="crop-area"]', 'Uitsnede origineel · 2400 × 1600')
        ->assertSeeIn('[data-slot="crop-save"]', 'Opslaan')
        ->click('[data-ratio="16:9"]')
        ->assertSeeIn('[data-slot="crop-area"]', 'Uitsnede 16:9 · 2400 × 1350')
        ->type('[data-slot="crop-alt"]', 'De kampioenen van 2026')
        ->click('[data-slot="crop-save"]');

    expect($shell->script(waitUntil("! document.querySelector('[data-media-crop]').open")))->toBeTrue()
        ->and($shell->script(waitUntil("document.querySelector('[data-slot=\"media-picker-alt\"]').textContent === 'Alt: De kampioenen van 2026'")))->toBeTrue();

    $upload = Media::query()->where('name', 'kampioenen-2026')->sole();

    expect([$upload->width, $upload->height])->toBe([1920, 1080])
        ->and($upload->crop)->toBe(['x' => 0, 'y' => 125, 'width' => 2400, 'height' => 1350, 'rotation' => 0, 'flip' => false, 'scale_down' => true])
        // The tile shows the new thumbnail, never one that left the disk.
        ->and($shell->script(waitUntil("document.querySelector('".pickerTile($upload)." [data-slot=\"media-tile-thumb\"]')?.src.endsWith('".$upload->thumb_path."')")))->toBeTrue();

    $shell->click('[data-slot="media-picker-use"]');

    expect($shell->script(waitUntil(pickedState('foto', 'filled'))))->toBeTrue()
        ->and($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and(draftOf($this->page, 'hero')['values']['foto'] ?? null)->toBe($upload->id);

    $shell->assertSeeIn('[data-path="foto"] [data-slot="image-meta"]', '1920 × 1080')
        ->assertSeeIn('[data-path="foto"] [data-slot="image-alt"]', 'Alt: De kampioenen van 2026')
        ->assertNoJavaScriptErrors();
});

/**
 * A script for $page->script() that holds the answer to the next upload once its bytes are in, so the server seems to
 * be scaling the photo down for as long as the test looks: window.answer() lets the field hear it.
 */
function holdAnswer(): string
{
    return <<<'JS'
        () => {
            const listen = XMLHttpRequest.prototype.addEventListener;

            XMLHttpRequest.prototype.addEventListener = function (type, listener, ...rest) {
                if (type !== 'load') {
                    return listen.call(this, type, listener, ...rest);
                }

                XMLHttpRequest.prototype.addEventListener = listen;

                return listen.call(this, type, (event) => {
                    window.answer = () => listener.call(this, event);
                }, ...rest);
            };
        }
        JS;
}

it('leaves a photo the server was still scaling down when the picker closed out of the crop step', function (): void {
    $shell = openStrip($this->page, 'hero', pickedState('foto', 'empty'));

    $shell->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil(pickerShows(['clubavond', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue();

    $shell->script(holdAnswer());
    $shell->attach('[data-media-picker] [data-picker-upload="one"] [data-slot="upload-input"]', quadrants(1600, 1200, 'jpg', 'Kampioenen 2026.jpg'));

    // The bytes are in and the answer waits: the field says the photo is being scaled down.
    expect($shell->script(waitUntil("typeof window.answer === 'function' && document.querySelector('[data-media-picker] [data-picker-upload=one] [data-upload] li')?.dataset.state === 'processing'")))->toBeTrue();

    $shell->keys('[data-media-picker] [data-slot="media-search"]', 'Escape');

    expect($shell->script(waitUntil("! document.querySelector('[data-media-picker]').open")))->toBeTrue();

    $shell->script('() => window.answer()');

    expect($shell->script(valueAfter("document.querySelector('[data-media-crop]').open")))->toBeFalse()
        ->and(Media::query()->where('name', 'kampioenen-2026')->exists())->toBeTrue()
        ->and($shell->script('() => document.activeElement?.dataset.slot'))->toBe('image-pick');

    // The next picker finishes the field: the photo asks nothing any more.
    $shell->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil(pickerShows(['kampioenen-2026', 'clubavond', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue()
        ->and($shell->script(waitUntil(pickerUploadMoment('rest'))))->toBeTrue();

    $shell->assertNoJavaScriptErrors();
});

it('asks the alt text in the upload field when the crop step does not take the photo', function (): void {
    $shell = openStrip($this->page, 'hero', pickedState('foto', 'empty'));

    // As if crop.js did not load: the dialog is there, but nothing listens to it.
    $shell->script("() => { const step = document.querySelector('[data-media-crop]'); step.replaceWith(step.cloneNode(true)); }");

    uploadInPicker($shell, 'Kampioenen 2026.jpg', quadrants(1600, 1200, 'jpg', 'Kampioenen 2026.jpg'));

    expect($shell->script("() => document.querySelector('[data-media-crop]').open"))->toBeFalse();

    $shell->type('[data-media-picker] [data-picker-upload="one"] [data-slot="upload-row-alt-input"]', 'De kampioenen van 2026')
        ->keys('[data-media-picker] [data-picker-upload="one"] [data-slot="upload-row-alt-input"]', 'Escape');

    expect($shell->script(waitUntil("! document.querySelector('[data-media-picker]').open")))->toBeTrue()
        ->and(Media::query()->where('name', 'kampioenen-2026')->sole()->alt)->toBe('De kampioenen van 2026');
});

it('stores the alt text typed for a photo uploaded in the picker when it is used at once', function (): void {
    $shell = openStrip($this->page, 'hero', pickedState('foto', 'empty'));

    uploadInPicker($shell, 'Kampioenen 2026.svg');

    // No "Alt-tekst opslaan": "Deze gebruiken" stores the text first.
    $shell->type('[data-media-picker] [data-picker-upload="one"] [data-slot="upload-row-alt-input"]', 'De kampioenen van 2026')
        ->click('[data-slot="media-picker-use"]');

    expect($shell->script(waitUntil(pickedState('foto', 'filled'))))->toBeTrue()
        ->and($shell->script(waitUntil(savedAsDraft())))->toBeTrue();

    $upload = Media::query()->where('name', 'kampioenen-2026')->sole();

    expect($upload->alt)->toBe('De kampioenen van 2026')
        ->and(draftOf($this->page, 'hero')['values']['foto'] ?? null)->toBe($upload->id)
        ->and($shell->script(waitUntil(siteInFrame()."?.querySelector('[data-cms-field=\"foto\"]')?.getAttribute('alt') === 'De kampioenen van 2026'")))->toBeTrue();

    $shell->assertSeeIn('[data-path="foto"] [data-slot="image-alt"]', 'Alt: De kampioenen van 2026')
        ->assertMissing('[data-path="foto"] [data-slot="image-no-alt"]');

    // The next time the picker opens, its upload field is at rest: it no longer asks about the former photo.
    $shell->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil(pickerShows(['kampioenen-2026', 'clubavond', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue()
        ->and($shell->script(pickerUploadMoment('rest')))->toBeTrue();

    $shell->assertMissing('[data-media-picker] [data-picker-upload="one"] [data-slot="upload-row-alt-input"]')
        ->assertNoJavaScriptErrors();
});

it('stores the alt text typed for a photo uploaded in the picker when the picker closes with Escape', function (): void {
    $shell = openStrip($this->page, 'hero', pickedState('foto', 'empty'));

    uploadInPicker($shell, 'Kampioenen 2026.svg');

    $shell->type('[data-media-picker] [data-picker-upload="one"] [data-slot="upload-row-alt-input"]', 'De kampioenen van 2026')
        ->keys('[data-media-picker] [data-picker-upload="one"] [data-slot="upload-row-alt-input"]', 'Escape');

    expect($shell->script(waitUntil("! document.querySelector('[data-media-picker]').open")))->toBeTrue()
        ->and($shell->script(waitUntil("document.activeElement?.dataset.slot === 'image-pick'")))->toBeTrue()
        ->and(Media::query()->where('name', 'kampioenen-2026')->sole()->alt)->toBe('De kampioenen van 2026')
        ->and($shell->script(pickedState('foto', 'empty')))->toBeTrue();

    $shell->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil(pickerShows(['kampioenen-2026', 'clubavond', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue()
        ->and($shell->script(pickerUploadMoment('rest')))->toBeTrue();

    $shell->assertNoJavaScriptErrors();
});

it('leaves the alt text of a photo uploaded in the picker for later when none was typed', function (): void {
    $shell = openStrip($this->page, 'hero', pickedState('foto', 'empty'));

    uploadInPicker($shell, 'Kampioenen 2026.svg');

    $shell->click('[data-slot="media-picker-use"]');

    expect($shell->script(waitUntil(pickedState('foto', 'filled'))))->toBeTrue()
        ->and($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and(Media::query()->where('name', 'kampioenen-2026')->sole()->alt)->toBeNull();

    $shell->assertVisible('[data-path="foto"] [data-slot="image-no-alt"]')
        ->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil(pickerShows(['kampioenen-2026', 'clubavond', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue()
        ->and($shell->script(pickerUploadMoment('rest')))->toBeTrue();

    $shell->assertNoJavaScriptErrors();
});

it('sends the folder it opened again when the session did not take it', function (): void {
    $shell = openStrip($this->page, 'hero', pickedState('foto', 'empty'));

    // The first time the folder goes to the server the request fails.
    $shell->script(<<<'JS'
        () => {
            const original = window.fetch;

            window.folderPuts = 0;
            window.folderStored = null;
            window.fetch = (input, init = {}) => {
                if (! String(input).includes('chosen-folder')) {
                    return original(input, init);
                }

                window.folderPuts++;

                return window.folderPuts === 1
                    ? Promise.reject(new TypeError('Failed to fetch'))
                    : original(input, init).then((response) => {
                        window.folderStored = response.status;

                        return response;
                    });
            };
        }
        JS);
    $shell->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil(pickerShows(['clubavond', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue();

    $shell->click('[data-media-picker] .kmap[data-folder="'.$this->teams->id.'"]');

    expect($shell->script(waitUntil('window.folderPuts === 1')))->toBeTrue();

    $shell->click('[data-slot="media-picker-cancel"]')
        ->click('[data-path="foto"] [data-slot="image-pick"]');

    // Opened again in the same folder, the picker sends it once more.
    expect($shell->script(waitUntil(pickerShows(['training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue()
        ->and($shell->script(waitUntil('window.folderStored === 204')))->toBeTrue()
        ->and($shell->script('() => window.folderPuts'))->toBe(2);

    $again = openStrip($this->page, 'hero', pickedState('foto', 'empty'));

    expect($again->script("() => JSON.parse(document.querySelector('script[data-config]').textContent).folder?.name"))->toBe('Teams');

    $again->assertNoJavaScriptErrors();
});

it('shows more images a page at a time', function (): void {
    Media::factory()->count(ListMedia::PER_PAGE + 2)->create(['created_at' => now()->subWeek()]);

    $shell = openStrip($this->page, 'hero', pickedState('foto', 'empty'));

    $shell->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil("document.querySelectorAll('[data-media-picker] .tegel').length === ".ListMedia::PER_PAGE)))->toBeTrue();

    $shell->assertSeeIn('[data-slot="media-status"]', (ListMedia::PER_PAGE + 5).' afbeeldingen')
        ->assertVisible('[data-slot="media-more"]')
        ->click('[data-slot="media-more"]');

    expect($shell->script(waitUntil("document.querySelectorAll('[data-media-picker] .tegel').length === ".(ListMedia::PER_PAGE + 5))))->toBeTrue()
        ->and($shell->script("() => [...document.querySelectorAll('[data-media-picker] .tegel')].indexOf(document.activeElement)"))->toBe(ListMedia::PER_PAGE);

    $shell->assertMissing('[data-slot="media-more"]')->assertNoJavaScriptErrors();
});

it('follows the dark colours of the system', function (): void {
    $shell = visit('/made-cms/pages/'.$this->page->id.'/edit')->inDarkMode();

    expect($shell->script(waitUntil(siteInFrame()."?.querySelector('made-cms-outlines')?.shadowRoot?.querySelectorAll('.omlijning').length === 2")))->toBeTrue();

    $shell->withinFrame('.bewerkframe', fn ($frame) => $frame->click('[data-strip="hero"] .tab'));

    expect($shell->script(waitUntil(pickedState('foto', 'empty'))))->toBeTrue();

    $shell->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil(pickerShows(['clubavond', 'training-dinsdag', 'teamfoto-merwestad']))))->toBeTrue();

    $shell->click(pickerTile($this->teamfoto));

    // The border of the choice comes in with a short transition.
    expect($shell->script(waitUntil("getComputedStyle(document.querySelector('[data-media-picker] .tegel[aria-pressed=\"true\"] .tegel-beeld')).borderTopColor === 'rgb(190, 138, 28)'")))->toBeTrue()
        ->and($shell->script(<<<'JS'
        () => {
            const colour = (selector, property) => getComputedStyle(document.querySelector(selector))[property];

            return [
                colour('[data-media-picker]', 'backgroundColor'),
                colour('[data-media-picker] .kiezer-voet', 'backgroundColor'),
                colour('[data-media-picker] .tegel-beeld', 'backgroundColor'),
                colour('[data-media-picker] .tegel[aria-pressed="true"] .tegel-beeld', 'borderTopColor'),
                colour('[data-media-picker] .kiezer-titel h2', 'color'),
                colour('[data-media-picker] .kmap[aria-pressed="true"]', 'borderLeftColor'),
            ];
        }
        JS))->toBe(['rgb(14, 43, 38)', 'rgb(27, 61, 54)', 'rgb(27, 61, 54)', 'rgb(190, 138, 28)', 'rgb(243, 241, 232)', 'rgb(190, 138, 28)']);

    $shell->assertNoJavaScriptErrors();
});
