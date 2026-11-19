<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\SyncMediaUsage;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;

const KAART_TRAINEN = '2c5a7d1e-3f4b-4c8d-a9e0-1b2c3d4e5f6a';

beforeEach(function (): void {
    config(['app.url' => 'http://localhost']);
    Storage::fake('public');

    // The fake media disk has no address of its own: this serves its files, so a thumbnail loads. It goes before the
    // site, whose pages take every address that is left.
    Route::get('storage/{path}', fn (string $path) => response()->file(Storage::disk('public')->path($path)))->where('path', '.*');
    useWorkbenchSite();

    $this->page = Page::factory()->published()->at('/teams')->withStrips([
        storedStrip('hero', ['kop' => 'Onze teams'], id: 'hero'),
        storedStrip('cards', ['kop' => 'Wat we doen', 'kaarten' => [['id' => KAART_TRAINEN, 'titel' => 'Trainen']]], id: 'cards'),
    ])->create(['title' => 'Teams']);

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * A JavaScript expression for one slot of the media element of a field.
 */
function mediaSlot(string $path, string $slot): string
{
    return "document.querySelector('[data-path=\"{$path}\"] [data-slot=\"{$slot}\"]')";
}

/**
 * A JavaScript condition: the media element of a field shows a photo, none, or a file that left Media.
 */
function mediaState(string $path, string $state): string
{
    return mediaSlot($path, 'image-card')."?.dataset.state === '{$state}'";
}

/**
 * A script for $page->script() that holds the next upload, so it stays on its way for as long as the test looks:
 * window.release() sends it, window.drop() lets its connection drop.
 */
function holdUpload(): string
{
    return <<<'JS'
        () => {
            const send = XMLHttpRequest.prototype.send;

            XMLHttpRequest.prototype.send = function (...body) {
                XMLHttpRequest.prototype.send = send;
                window.release = () => send.apply(this, body);
                window.drop = () => this.dispatchEvent(new ProgressEvent('error'));
            };
        }
        JS;
}

/**
 * A JavaScript condition: the line at the foot of the side panel says this.
 */
function uploadStatus(string $text): string
{
    $quoted = json_encode($text, JSON_THROW_ON_ERROR);

    return "document.querySelector('[data-slot=\"upload-status\"]').checkVisibility() && document.querySelector('[data-slot=\"upload-status-text\"]').textContent === {$quoted}";
}

it('uploads a photo into an empty media element, makes it the value of the field and shows it on the site', function (): void {
    $shell = openStrip($this->page, 'hero', mediaState('foto', 'empty'));

    $shell->assertSeeIn('[data-path="foto"] .beeldveld-kop [data-slot="hint"]', 'Minimaal 1600 px breed.')
        ->assertSeeIn('[data-path="foto"] [data-slot="image-empty"]', 'Nog geen afbeelding')
        ->assertSeeIn('[data-path="foto"] [data-slot="image-empty-text"]', 'Zonder afbeelding laat de site zijn eigen standaard zien.')
        ->assertMissing('[data-path="foto"] [data-slot="image-remove"]')
        ->assertMissing('[data-path="foto"] [data-upload]')
        ->click('[data-path="foto"] [data-slot="image-upload"]');

    // The upload field opens below the element for one photo, with the focus on choosing it. Its file input stays
    // out of sight, also among the styles of the fields of the panel, so the shell never grows wider than the window.
    expect($shell->script(waitUntil("document.activeElement?.dataset.slot === 'upload-choose'")))->toBeTrue()
        ->and($shell->script("() => document.querySelector('[data-path=\"foto\"] [data-slot=\"upload-input\"]').getBoundingClientRect().width"))->toBe(1)
        ->and($shell->script('() => document.documentElement.scrollWidth <= document.documentElement.clientWidth'))->toBeTrue();

    $shell->assertAttribute('[data-path="foto"] [data-slot="image-upload"]', 'aria-expanded', 'true')
        ->assertSeeIn('[data-path="foto"] [data-slot="upload-title"]', 'Sleep je foto hierheen')
        ->assertSeeIn('[data-path="foto"] [data-slot="upload-rules"]', 'komt in Alle bestanden')
        ->attach('[data-path="foto"] [data-slot="upload-input"]', photo(2400, 1600, 'jpg', 'Teamfoto Merwestad.jpg'));

    expect($shell->script(waitUntil(mediaState('foto', 'filled'))))->toBeTrue()
        ->and($shell->script(waitUntil(savedAsDraft())))->toBeTrue();

    $media = Media::query()->sole();

    expect($media->folder_id)->toBeNull()
        ->and(draftOf($this->page, 'hero')['values']['foto'] ?? null)->toBe($media->id)
        ->and($shell->script('() => '.mediaSlot('foto', 'image-extension').'.textContent'))->toBe('webp')
        ->and($shell->script(waitUntil(mediaSlot('foto', 'image-thumb').'.naturalWidth > 0')))->toBeTrue()
        // The site printed no image yet, so the frame reloads with it once the draft is saved.
        ->and($shell->script(waitUntil(siteInFrame()."?.querySelector('[data-cms-strip=\"hero\"] img[data-cms-field=\"foto\"]')?.getAttribute('src') === ".json_encode(Storage::disk('public')->url($media->path)))))->toBeTrue();

    $shell->assertSeeIn('[data-path="foto"] [data-slot="image-name"]', 'teamfoto-merwestad')
        ->assertSeeIn('[data-path="foto"] [data-slot="image-meta"]', '1920 × 1280 · ')
        ->assertSeeIn('[data-path="foto"] [data-slot="image-alt"]', 'Nog geen alt-tekst — zet die in Media, dan geldt hij overal.')
        ->assertVisible('[data-path="foto"] [data-slot="image-no-alt"]')
        ->assertVisible('[data-path="foto"] [data-slot="image-remove"]')
        // The moment "done" asks the alt text; the photo is the value of the field already.
        ->type('[data-path="foto"] [data-slot="upload-row-alt-input"]', 'Het eerste team voor de tafels')
        ->keys('[data-path="foto"] [data-slot="upload-row-alt-input"]', 'Enter');

    expect($shell->script(waitUntil("! document.querySelector('[data-path=\"foto\"] [data-upload]')")))->toBeTrue()
        ->and($media->refresh()->alt)->toBe('Het eerste team voor de tafels')
        ->and($shell->script(waitUntil(siteInFrame()."?.querySelector('[data-cms-field=\"foto\"]')?.alt === 'Het eerste team voor de tafels'")))->toBeTrue()
        ->and($shell->script('() => document.activeElement?.dataset.slot'))->toBe('image-upload')
        ->and(MediaUsage::query()->onPage($this->page)->get(['media_id', 'version', 'field'])->toArray())->toBe([
            ['media_id' => $media->id, 'version' => 'draft', 'field' => 'foto'],
        ]);

    // Next to the field the photo showed itself, so the line at the foot of the panel says nothing.
    $shell->assertSeeIn('[data-path="foto"] [data-slot="image-alt"]', 'Alt: Het eerste team voor de tafels')
        ->assertMissing('[data-path="foto"] [data-slot="image-no-alt"]')
        ->assertAttribute('[data-path="foto"] [data-slot="image-upload"]', 'aria-expanded', 'false')
        ->assertMissing('[data-slot="upload-status"]')
        ->assertNoJavaScriptErrors();
});

it('shows the photo a field holds with its folder, focal point and alt text, and takes it away with ×', function (): void {
    $photo = Media::factory()->in(MediaFolder::factory()->create(['name' => 'Teams']))->withAlt('Het eerste team')->create([
        'name' => 'teamfoto',
        'path' => 'media/01k9/teamfoto.webp',
        'width' => 1600,
        'height' => 1067,
        'size' => 412_000,
    ]);
    $this->page->update(['strips' => [storedStrip('hero', ['kop' => 'Onze teams', 'foto' => $photo->id], id: 'hero'), $this->page->strips[1]]]);

    $shell = openStrip($this->page, 'hero', mediaState('foto', 'filled'));

    $shell->assertSeeIn('[data-path="foto"] [data-slot="image-name"]', 'teamfoto')
        ->assertSeeIn('[data-path="foto"] [data-slot="image-meta"]', '1600 × 1067 · 412 kB · uit map Teams')
        ->assertSeeIn('[data-path="foto"] [data-slot="image-alt"]', 'Alt: Het eerste team')
        ->assertMissing('[data-path="foto"] [data-slot="image-no-alt"]')
        // The dots say what they show, and × names the field it empties.
        ->assertAttribute('[data-path="foto"] [data-slot="image-focal"]', 'role', 'img')
        ->assertAttribute('[data-path="foto"] [data-slot="image-focal"]', 'aria-label', 'Brandpunt')
        ->assertAttribute('[data-path="foto"] [data-slot="image-no-alt"]', 'role', 'img')
        ->assertAttribute('[data-path="foto"] [data-slot="image-no-alt"]', 'aria-label', 'Geen alt-tekst')
        ->assertAttribute('[data-path="foto"] [data-slot="image-remove"]', 'aria-label', 'Foto weghalen')
        ->assertAttribute('[data-path="foto"]', 'role', 'group');

    expect($shell->script('() => '.mediaSlot('foto', 'image-focal').'.style.left'))->toBe('50%')
        ->and($shell->script('() => '.mediaSlot('foto', 'image-thumb').'.style.objectPosition'))->toBe('50% 50%');

    $shell->click('[data-path="foto"] [data-slot="image-remove"]');

    expect($shell->script(waitUntil(mediaState('foto', 'empty'))))->toBeTrue()
        ->and($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and(array_key_exists('foto', draftOf($this->page, 'hero')['values']) ? draftOf($this->page, 'hero')['values']['foto'] : null)->toBeNull()
        ->and($shell->script('() => document.activeElement?.dataset.slot'))->toBe('image-upload')
        ->and($shell->script(waitUntil('! '.siteInFrame()."?.querySelector('[data-cms-field=\"foto\"]')")))->toBeTrue();

    $shell->assertNoJavaScriptErrors();
});

it('crops the photo of a field in the crop step, and the site shows the new crop', function (): void {
    $photo = UploadMedia::run(new UploadedMedia(quadrants(2400, 1600), 'teamfoto.jpg'));
    $this->page->update(['strips' => [storedStrip('hero', ['kop' => 'Onze teams', 'foto' => $photo->id], id: 'hero'), $this->page->strips[1]]]);
    SyncMediaUsage::run($this->page);

    $shell = openStrip($this->page, 'hero', mediaState('foto', 'filled'));

    $shell->assertSeeIn('[data-path="foto"] [data-slot="image-crop"]', 'Bijsnijden')
        ->assertAttribute('[data-path="foto"] [data-slot="image-crop"]', 'aria-haspopup', 'dialog')
        ->assertSeeIn('[data-path="foto"] [data-slot="image-meta"]', '1920 × 1280')
        ->click('[data-path="foto"] [data-slot="image-crop"]');

    expect($shell->script(waitUntil("document.querySelector('[data-media-crop]').open && ! document.querySelector('[data-slot=\"crop-save\"]').disabled")))->toBeTrue();

    $shell->assertSeeIn('[data-slot="crop-eyebrow"]', 'Media / bijsnijden')
        ->assertSeeIn('[data-slot="crop-used"]', 'Staat op 1 pagina. Bijsnijden verandert hem daar meteen, ook op de gepubliceerde site.')
        ->script(markFrame());

    $shell->click('[data-ratio="16:9"]')
        ->keys('[data-slot="crop-focal"]', 'Shift+ArrowLeft')
        ->click('[data-slot="crop-save"]');

    $photo->refresh();

    expect($shell->script(waitUntil("! document.querySelector('[data-media-crop]').open")))->toBeTrue()
        ->and($shell->script(waitUntil(mediaSlot('foto', 'image-meta').".textContent.startsWith('1920 × 1080')")))->toBeTrue()
        ->and($shell->script('() => document.activeElement?.dataset.slot'))->toBe('image-crop')
        ->and($shell->script('() => '.mediaSlot('foto', 'image-focal').'.style.left'))->toBe('40%')
        ->and($shell->script(waitUntil(frameReloaded())))->toBeTrue()
        ->and($shell->script(waitUntil(siteInFrame()."?.querySelector('[data-cms-field=\"foto\"]')?.getAttribute('src')?.endsWith('".$photo->path."')")))->toBeTrue()
        ->and($shell->script('() => '.siteInFrame().".querySelector('[data-cms-field=\"foto\"]').style.objectPosition"))->toBe('40% 50%')
        // The field still holds the file: a crop changes the file, not the draft.
        ->and(draftOf($this->page, 'hero')['values']['foto'] ?? null)->toBe($photo->id)
        ->and($this->page->refresh()->draft_strips)->toBeNull();

    $shell->assertNoJavaScriptErrors();
});

it('offers no crop for an image without an original to cut from', function (): void {
    $logo = UploadMedia::run(new UploadedMedia(mediaFixture('logo.svg'), 'logo.svg'));
    $this->page->update(['strips' => [storedStrip('hero', ['kop' => 'Onze teams', 'foto' => $logo->id], id: 'hero'), $this->page->strips[1]]]);

    $shell = openStrip($this->page, 'hero', mediaState('foto', 'filled'));

    $shell->assertMissing('[data-path="foto"] [data-slot="image-crop"]')
        ->assertVisible('[data-path="foto"] [data-slot="image-pick"]');
});

it('says that the file of a field left Media and lets the editor take it away', function (): void {
    $this->page->update(['strips' => [storedStrip('hero', ['kop' => 'Onze teams', 'foto' => missingId()], id: 'hero'), $this->page->strips[1]]]);

    $shell = openStrip($this->page, 'hero', mediaState('foto', 'gone'));

    $shell->assertSeeIn('[data-path="foto"] [data-slot="image-gone"]', 'Deze afbeelding staat niet meer in Media.')
        ->assertSeeIn('[data-path="foto"] [data-slot="image-gone"]', 'De site laat hier nu zijn eigen standaard zien.')
        ->assertVisible('[data-path="foto"] [data-slot="image-gone-icon"]')
        ->assertMissing('[data-path="foto"] [data-slot="image-empty"]')
        ->assertVisible('[data-path="foto"] [data-slot="image-upload"]')
        ->click('[data-path="foto"] [data-slot="image-remove"]');

    expect($shell->script(waitUntil(mediaState('foto', 'empty'))))->toBeTrue();

    $shell->assertNoJavaScriptErrors();
});

it('does the same in an item of a list', function (): void {
    $shell = openStrip($this->page, 'cards', "document.querySelectorAll('[data-path=\"kaarten\"] li').length === 1");

    $shell->click('[data-path="kaarten"] li:nth-child(1) [data-slot="edit"]')
        ->assertSeeIn('[data-slot="crumb-item"]', 'Trainen')
        ->assertSeeIn('[data-path="kaarten.0.icoon"] [data-slot="hint"]', 'Vierkant werkt het best.')
        ->click('[data-path="kaarten.0.icoon"] [data-slot="image-upload"]')
        ->attach('[data-path="kaarten.0.icoon"] [data-slot="upload-input"]', photo(600, 600, 'png', 'Icoon training.png'));

    expect($shell->script(waitUntil(mediaState('kaarten.0.icoon', 'filled'))))->toBeTrue()
        ->and($shell->script(waitUntil(savedAsDraft())))->toBeTrue();

    $media = Media::query()->sole();

    expect(draftOf($this->page, 'cards')['values']['kaarten'][0]['icoon'] ?? null)->toBe($media->id)
        ->and(draftOf($this->page, 'cards')['values']['kaarten'][0]['id'] ?? null)->toBe(KAART_TRAINEN)
        ->and($shell->script(waitUntil(siteInFrame()."?.querySelector('[data-cms-items=\"kaarten\"] img[data-cms-field=\"icoon\"]')")))->toBeTrue();

    $shell->click('[data-path="kaarten.0.icoon"] [data-slot="upload-later"]')
        ->assertAttribute('[data-path="kaarten.0.icoon"] [data-slot="image-remove"]', 'aria-label', 'Icoon weghalen')
        ->click('[data-path="kaarten.0.icoon"] [data-slot="image-remove"]');

    expect($shell->script(waitUntil(mediaState('kaarten.0.icoon', 'empty'))))->toBeTrue()
        ->and($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and(draftOf($this->page, 'cards')['values']['kaarten'][0]['icoon'] ?? null)->toBeNull();

    $shell->assertSeeIn('[data-slot="crumb-item"]', 'Trainen')
        ->assertNoJavaScriptErrors();
});

it('refuses a document dropped on the field of an image, and closes the field again', function (): void {
    $shell = openStrip($this->page, 'hero', mediaState('foto', 'empty'));

    $shell->click('[data-path="foto"] [data-slot="image-upload"]');

    $pdf = base64_encode((string) file_get_contents(mediaFixture('wedstrijdschema.pdf')));

    $shell->script(<<<JS
        () => {
            const transfer = new DataTransfer();
            const field = document.querySelector('[data-path="foto"] [data-upload]');

            transfer.items.add(new File([Uint8Array.from(atob('{$pdf}'), (c) => c.charCodeAt(0))], 'Wedstrijdschema.pdf', { type: 'application/pdf' }));
            ['dragenter', 'dragover', 'drop'].forEach((type) => field.dispatchEvent(new DragEvent(type, { bubbles: true, cancelable: true, dataTransfer: transfer })));
        }
        JS);

    expect($shell->script(waitUntil("document.querySelector('[data-path=\"foto\"] [data-upload]').dataset.moment === 'error'")))->toBeTrue()
        ->and(Media::query()->count())->toBe(0);

    $shell->assertSeeIn('[data-path="foto"] [data-slot="upload-row-note"]', 'Dit veld neemt alleen foto’s.')
        ->assertSeeIn('[data-path="foto"] [data-slot="image-empty"]', 'Nog geen afbeelding')
        ->click('[data-path="foto"] [data-slot="image-upload-cancel"]');

    expect($shell->script(waitUntil("! document.querySelector('[data-path=\"foto\"] [data-upload]')")))->toBeTrue()
        ->and($shell->script('() => document.activeElement?.dataset.slot'))->toBe('image-upload');

    $shell->assertAttribute('[data-path="foto"] [data-slot="image-upload"]', 'aria-expanded', 'false')
        ->assertNoJavaScriptErrors();
});

it('keeps uploading while the editor works on another strip, says so at the foot of the panel and puts the photo in its field', function (): void {
    $shell = openStrip($this->page, 'hero', mediaState('foto', 'empty'));

    $shell->script(holdUpload());
    $shell->click('[data-path="foto"] [data-slot="image-upload"]')
        ->attach('[data-path="foto"] [data-slot="upload-input"]', photo(1200, 800, 'jpg', 'Clubavond.jpg'));

    expect($shell->script(waitUntil(uploadStatus('Foto uploaden voor Hero › Foto'))))->toBeTrue();

    // While the photo travels the field shows its row, says the editor can go on, and can still stop it.
    $shell->assertVisible('[data-path="foto"] [data-slot="image-upload-busy"]')
        ->assertVisible('[data-path="foto"] [data-slot="image-upload-cancel"]')
        ->assertMissing('[data-path="foto"] [data-slot="upload-zone"]')
        ->assertSeeIn('[data-slot="upload-status-percent"]', '%');

    // The editor goes on with the cards; the line stays.
    $shell->withinFrame('.bewerkframe', fn ($frame) => $frame->click('[data-strip="cards"] .tab'));

    expect($shell->script(waitUntil("document.querySelectorAll('[data-path=\"kaarten\"] li').length === 1")))->toBeTrue()
        ->and($shell->script(waitUntil(uploadStatus('Foto uploaden voor Hero › Foto'))))->toBeTrue();

    $shell->script('() => window.release()');

    expect($shell->script(waitUntil(uploadStatus('De foto staat in Hero › Foto.'))))->toBeTrue();

    $media = Media::query()->sole();

    // The editor puts the result away with its cross.
    $shell->assertAttribute('[data-slot="upload-status-close"]', 'aria-label', 'Melding sluiten')
        ->assertMissing('[data-slot="upload-status-open"]')
        ->click('[data-slot="upload-status-close"]')
        ->assertMissing('[data-slot="upload-status"]');

    // Coming back shows the photo, saved as the draft, and the moment "done" of its field.
    $shell->withinFrame('.bewerkframe', fn ($frame) => $frame->click('[data-strip="hero"] .tab'));

    expect($shell->script(waitUntil(mediaState('foto', 'filled').' && '.savedAsDraft())))->toBeTrue()
        ->and(draftOf($this->page, 'hero')['values']['foto'] ?? null)->toBe($media->id)
        ->and($shell->script("() => document.querySelector('[data-path=\"foto\"] [data-upload]')?.dataset.moment"))->toBe('done');

    $shell->assertNoJavaScriptErrors();
});

it('says at the foot of the panel when an upload out of sight failed, until the editor opens its field', function (): void {
    $shell = openStrip($this->page, 'hero', mediaState('foto', 'empty'));

    $shell->script(holdUpload());
    $shell->click('[data-path="foto"] [data-slot="image-upload"]')
        ->attach('[data-path="foto"] [data-slot="upload-input"]', photo(1200, 800, 'jpg', 'Clubavond.jpg'));

    expect($shell->script(waitUntil(uploadStatus('Foto uploaden voor Hero › Foto'))))->toBeTrue();

    $shell->withinFrame('.bewerkframe', fn ($frame) => $frame->click('[data-strip="cards"] .tab'));

    expect($shell->script(waitUntil("document.querySelectorAll('[data-path=\"kaarten\"] li').length === 1")))->toBeTrue();

    $shell->script('() => window.drop()');

    expect($shell->script(waitUntil(uploadStatus('Uploaden voor Hero › Foto lukte niet. Open dat veld om het opnieuw te proberen.'))))->toBeTrue()
        ->and($shell->script("() => document.querySelector('[data-slot=\"upload-status\"]').dataset.state"))->toBe('failed');

    $shell->click('[data-slot="upload-status-open"]');

    expect($shell->script(waitUntil("document.querySelector('[data-path=\"foto\"] [data-upload]')?.dataset.moment === 'error'")))->toBeTrue()
        ->and($shell->script(waitUntil("document.activeElement?.dataset.slot === 'image-upload'")))->toBeTrue()
        ->and($shell->script(waitUntil("! document.querySelector('[data-slot=\"upload-status\"]').checkVisibility()")))->toBeTrue()
        ->and(Media::query()->count())->toBe(0);

    $shell->assertVisible('[data-path="foto"] [data-slot="upload-row-retry"]')
        ->assertNoJavaScriptErrors();
});

it('stops a photo that is still on its way when the editor cancels, and keeps the field as it was', function (): void {
    $shell = openStrip($this->page, 'hero', mediaState('foto', 'empty'));

    $shell->script(holdUpload());
    $shell->click('[data-path="foto"] [data-slot="image-upload"]')
        ->attach('[data-path="foto"] [data-slot="upload-input"]', photo(1200, 800, 'jpg', 'Clubavond.jpg'));

    expect($shell->script(waitUntil(uploadStatus('Foto uploaden voor Hero › Foto'))))->toBeTrue();

    $shell->click('[data-path="foto"] [data-slot="image-upload-cancel"]');

    expect($shell->script(waitUntil("! document.querySelector('[data-path=\"foto\"] [data-upload]')")))->toBeTrue()
        ->and($shell->script("() => document.querySelector('[data-slot=\"upload-status\"]').checkVisibility()"))->toBeFalse()
        ->and($shell->script('() => document.activeElement?.dataset.slot'))->toBe('image-upload')
        ->and(Media::query()->count())->toBe(0);

    $shell->assertAttribute('[data-path="foto"] [data-slot="image-upload"]', 'aria-expanded', 'false')
        ->assertNoJavaScriptErrors();
});

it('publishes the photo the editor is still uploading', function (): void {
    $shell = openStrip($this->page, 'hero', mediaState('foto', 'empty'));

    $shell->type('[data-path="kop"] input', 'Al onze teams');

    expect($shell->script(waitUntil("! document.querySelector('[data-slot=\"bar-publish\"]').disabled")))->toBeTrue();

    $shell->script(holdUpload());
    $shell->click('[data-path="foto"] [data-slot="image-upload"]')
        ->attach('[data-path="foto"] [data-slot="upload-input"]', photo(1200, 800, 'jpg', 'Clubavond.jpg'));

    expect($shell->script(waitUntil(uploadStatus('Foto uploaden voor Hero › Foto'))))->toBeTrue();

    $shell->click('[data-slot="bar-publish"]');
    $shell->script('() => window.release()');

    expect($shell->script(waitUntil("document.querySelector('[data-slot=\"bar-state\"]').textContent.trim() === 'Gepubliceerd'")))->toBeTrue();

    $page = Page::query()->findOrFail($this->page->id);

    expect($page->draft_strips)->toBeNull()
        ->and($page->strips[0]['values']['kop'] ?? null)->toBe(['text' => 'Al onze teams', 'tag' => 'h2'])
        ->and($page->strips[0]['values']['foto'] ?? null)->toBe(Media::query()->sole()->id);

    $shell->assertNoJavaScriptErrors();
});

it('follows the dark colours of the system', function (): void {
    $photo = Media::factory()->create(['name' => 'teamfoto', 'path' => 'media/01k9/teamfoto.webp', 'width' => 1600, 'height' => 1067]);
    $this->page->update(['strips' => [storedStrip('hero', ['kop' => 'Onze teams', 'foto' => $photo->id], id: 'hero'), $this->page->strips[1]]]);

    $shell = visit('/made-cms/pages/'.$this->page->id.'/edit')->inDarkMode();

    expect($shell->script(waitUntil(siteInFrame()."?.querySelector('made-cms-outlines')?.shadowRoot?.querySelectorAll('.omlijning').length === 2")))->toBeTrue();

    $shell->withinFrame('.bewerkframe', fn ($frame) => $frame->click('[data-strip="hero"] .tab'));

    expect($shell->script(waitUntil(mediaState('foto', 'filled'))))->toBeTrue()
        ->and($shell->script(<<<'JS'
            () => {
                const colour = (selector, property) => getComputedStyle(document.querySelector(selector))[property];

                return [
                    colour('body', 'backgroundColor'),
                    colour('[data-path="foto"] .beeldkaart', 'backgroundColor'),
                    colour('[data-path="foto"] .beeldkaart-voorbeeld', 'backgroundColor'),
                    colour('[data-path="foto"] .beeldkaart-naam', 'color'),
                    colour('[data-path="foto"] .beeldkaart-alt', 'color'),
                    colour('[data-path="foto"] .beeldkaart-stip', 'backgroundColor'),
                ];
            }
            JS))->toBe(['rgb(14, 43, 38)', 'rgb(27, 61, 54)', 'rgb(27, 61, 54)', 'rgb(243, 241, 232)', 'rgb(217, 166, 58)', 'rgb(190, 138, 28)']);

    $shell->assertNoJavaScriptErrors();
});
