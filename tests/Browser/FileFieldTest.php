<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;

const DOWNLOAD_SCHEMA = '6f1e2d3c-4b5a-4c6d-8e7f-9a0b1c2d3e4f';

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
    $this->schema = Media::factory()->in($this->teams)->document()->withAlt('Wedstrijdschema najaar (pdf)')->create(['name' => 'wedstrijdschema', 'size' => 1_240_000]);
    $this->video = Media::factory()->in($this->teams)->video()->create(['name' => 'samenvatting']);
    $this->photo = Media::factory()->in($this->teams)->create(['name' => 'teamfoto', 'path' => 'media/01k9/teamfoto.webp']);
    Storage::disk('public')->put($this->photo->path, (string) file_get_contents(photo(1600, 1067, 'webp')));

    $this->page = Page::factory()->published()->at('/downloads')->withStrips([
        storedStrip('hero', ['kop' => 'Downloads'], id: 'hero'),
        storedStrip('downloads', ['kop' => 'Voor de competitie', 'bestanden' => [['id' => DOWNLOAD_SCHEMA, 'titel' => null, 'bestand' => null]]], id: 'downloads'),
    ])->create(['title' => 'Downloads']);

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * A JavaScript condition: the media element of a file field shows a file, none, or one that is gone or does not fit.
 */
function fileState(string $path, string $state): string
{
    return "document.querySelector('[data-path=\"{$path}\"] [data-slot=\"file-card\"]')?.dataset.state === '{$state}'";
}

/**
 * Opens the downloads strip and the document of its first item.
 */
function openDownload(Page $page, string $state): mixed
{
    $shell = openStrip($page, 'downloads', "document.querySelectorAll('[data-path=\"bestanden\"] li').length === 1");

    $shell->click('[data-path="bestanden"] li:nth-child(1) [data-slot="edit"]');

    expect($shell->script(waitUntil(fileState('bestanden.0.bestand', $state))))->toBeTrue();

    return $shell;
}

/**
 * A JavaScript expression for the link of the first download on the site in the frame.
 */
function downloadLink(): string
{
    return siteInFrame()."?.querySelector('[data-cms-items=\"bestanden\"] a[data-cms-field=\"bestand\"]')";
}

it('chooses a pdf for a downloads strip in the picker, which shows only documents, and links to it on the site', function (): void {
    $shell = openDownload($this->page, 'empty');

    $shell->assertSeeIn('[data-path="bestanden.0.bestand"] [data-slot="file-empty"]', 'Nog geen bestand')
        ->assertSeeIn('[data-path="bestanden.0.bestand"] [data-slot="file-pick"]', 'Kiezen uit media')
        ->assertMissing('[data-path="bestanden.0.bestand"] [data-slot="file-remove"]')
        ->click('[data-path="bestanden.0.bestand"] [data-slot="file-pick"]');

    expect($shell->script(waitUntil(pickerShows(['wedstrijdschema']))))->toBeTrue()
        ->and($shell->script(pickerFolders()))->toBe([
            ['Alle bestanden', '1', 'true'],
            ['Teams', '1', 'false'],
        ]);

    $shell->assertSeeIn('#made-cms-kiezer-kop', 'Kies een document')
        ->assertSeeIn('[data-slot="media-picker-for"]', 'voor Bestand in Downloads › Document 1')
        ->assertSeeIn('[data-slot="media-kind"]', 'Documenten')
        ->assertSeeIn('[data-slot="media-status"]', '1 document')
        ->assertMissing('[data-media-picker] [data-slot="media-without-alt"]')
        ->assertMissing('[data-media-picker] [data-slot="media-picker-stand"]')
        ->assertMissing('[data-media-picker] [data-slot="media-picker-hint"]')
        ->assertVisible('[data-media-picker] [data-picker-upload="document"]')
        ->assertMissing('[data-media-picker] [data-picker-upload="one"]')
        ->assertSeeIn('[data-slot="media-picker-use"]', 'Kies eerst een document')
        ->click(pickerTile($this->schema))
        ->assertSeeIn('[data-slot="media-picker-alt"]', 'Omschrijving: Wedstrijdschema najaar (pdf)')
        ->assertAttribute('[data-slot="media-picker-mini"]', 'data-kind', 'document')
        ->click('[data-slot="media-picker-use"]');

    // The lasting link of the file, absolute from the address of the site.
    $link = '/made-cms/file/'.$this->schema->ulid.'/wedstrijdschema';

    expect($shell->script(waitUntil(fileState('bestanden.0.bestand', 'filled'))))->toBeTrue()
        ->and($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and(draftOf($this->page, 'downloads')['values']['bestanden'][0]['bestand'] ?? null)->toBe($this->schema->id)
        // The view prints the name, size and description, which nothing patches: the frame reloads with the link.
        ->and($shell->script(waitUntil('new URL('.downloadLink()."?.href ?? 'http://x/').pathname === ".json_encode($link))))->toBeTrue()
        ->and($shell->script('() => '.downloadLink().'.textContent'))->toBe('Wedstrijdschema najaar (pdf)')
        ->and(MediaUsage::query()->onPage($this->page)->pluck('media_id')->all())->toBe([$this->schema->id]);

    $shell->assertSeeIn('[data-path="bestanden.0.bestand"] [data-slot="file-name"]', 'wedstrijdschema')
        ->assertSeeIn('[data-path="bestanden.0.bestand"] [data-slot="file-extension"]', 'pdf')
        ->assertSeeIn('[data-path="bestanden.0.bestand"] [data-slot="file-meta"]', '1,2 MB · uit map Teams')
        ->assertSeeIn('[data-path="bestanden.0.bestand"] [data-slot="file-alt"]', 'Omschrijving: Wedstrijdschema najaar (pdf)')
        ->assertAttribute('[data-path="bestanden.0.bestand"] [data-slot="file-card"]', 'data-kind', 'document')
        ->assertNoJavaScriptErrors();

    // The picker of an image opens with the images again, with its filter and its own words.
    $shell->withinFrame('.bewerkframe', fn ($frame) => $frame->click('[data-strip="hero"] .tab'));

    expect($shell->script(waitUntil("document.querySelector('[data-path=\"foto\"] [data-slot=\"image-card\"]')?.dataset.state === 'empty'")))->toBeTrue();

    $shell->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil(pickerShows(['teamfoto']))))->toBeTrue();

    $shell->assertSeeIn('#made-cms-kiezer-kop', 'Kies een afbeelding')
        ->assertSeeIn('[data-slot="media-kind"]', 'Afbeeldingen')
        ->assertVisible('[data-media-picker] [data-slot="media-without-alt"]')
        ->assertVisible('[data-media-picker] [data-picker-upload="one"]')
        ->assertMissing('[data-media-picker] [data-picker-upload="document"]')
        ->assertSeeIn('[data-slot="media-picker-use"]', 'Kies eerst een afbeelding')
        ->assertNoJavaScriptErrors();
});

it('uploads a pdf into the element with its description, which the site shows as the text of the link', function (): void {
    $shell = openDownload($this->page, 'empty');

    $shell->click('[data-path="bestanden.0.bestand"] [data-slot="file-upload"]');

    expect($shell->script(waitUntil("document.activeElement?.dataset.slot === 'upload-choose'")))->toBeTrue();

    $shell->assertSeeIn('[data-path="bestanden.0.bestand"] [data-slot="upload-title"]', 'Sleep je document hierheen')
        ->assertSeeIn('[data-path="bestanden.0.bestand"] [data-slot="upload-rules"]', 'Documenten · tot 25 MB · komt in Alle bestanden')
        ->attach('[data-path="bestanden.0.bestand"] [data-slot="upload-input"]', mediaFixture('wedstrijdschema.pdf', 'Inschrijfformulier.pdf'));

    expect($shell->script(waitUntil(fileState('bestanden.0.bestand', 'filled'))))->toBeTrue()
        ->and($shell->script(waitUntil(savedAsDraft())))->toBeTrue();

    $media = Media::query()->where('name', 'inschrijfformulier')->sole();

    expect(draftOf($this->page, 'downloads')['values']['bestanden'][0]['bestand'] ?? null)->toBe($media->id);

    $shell->assertSeeIn('[data-path="bestanden.0.bestand"] [data-slot="file-alt"]', 'Geen omschrijving — de site toont dan de naam van het bestand.')
        ->assertSeeIn('[data-path="bestanden.0.bestand"] [data-slot="upload-row-alt-label"]', 'Omschrijving')
        ->type('[data-path="bestanden.0.bestand"] [data-slot="upload-row-alt-input"]', 'Inschrijfformulier 2026')
        ->keys('[data-path="bestanden.0.bestand"] [data-slot="upload-row-alt-input"]', 'Enter');

    expect($shell->script(waitUntil("! document.querySelector('[data-path=\"bestanden.0.bestand\"] [data-upload]')")))->toBeTrue()
        ->and($media->refresh()->alt)->toBe('Inschrijfformulier 2026')
        // The description is no value of the strip: the strip saves again and the frame shows it.
        ->and($shell->script(waitUntil(downloadLink()."?.textContent === 'Inschrijfformulier 2026'")))->toBeTrue();

    $shell->assertSeeIn('[data-path="bestanden.0.bestand"] [data-slot="file-alt"]', 'Omschrijving: Inschrijfformulier 2026')
        ->assertNoJavaScriptErrors();
});

it('refuses a photo for a field of documents before it travels', function (): void {
    $shell = openDownload($this->page, 'empty');

    $shell->click('[data-path="bestanden.0.bestand"] [data-slot="file-upload"]')
        ->attach('[data-path="bestanden.0.bestand"] [data-slot="upload-input"]', photo(1200, 800, 'jpg', 'Teamfoto.jpg'));

    expect($shell->script(waitUntil("document.querySelector('[data-path=\"bestanden.0.bestand\"] [data-upload]')?.dataset.moment === 'error'")))->toBeTrue()
        ->and(Media::query()->count())->toBe(3);

    $shell->assertSeeIn('[data-path="bestanden.0.bestand"] [data-slot="upload-row-note"]', 'Dit veld neemt alleen documenten.')
        ->assertSeeIn('[data-path="bestanden.0.bestand"] [data-slot="file-empty"]', 'Nog geen bestand')
        ->assertNoJavaScriptErrors();
});

it('takes a file away with ×, and says when its file left Media or is of a kind the field does not take', function (): void {
    $gone = Media::factory()->document()->create();
    $this->page->update(['strips' => [$this->page->strips[0], storedStrip('downloads', ['bestanden' => [
        ['id' => DOWNLOAD_SCHEMA, 'titel' => null, 'bestand' => $this->schema->id],
        ['id' => '6f1e2d3c-4b5a-4c6d-8e7f-9a0b1c2d3e50', 'titel' => 'Weg', 'bestand' => $gone->id],
        ['id' => '6f1e2d3c-4b5a-4c6d-8e7f-9a0b1c2d3e51', 'titel' => 'Video', 'bestand' => $this->video->id],
    ]], id: 'downloads')]]);
    $gone->delete();

    $shell = openStrip($this->page, 'downloads', "document.querySelectorAll('[data-path=\"bestanden\"] li').length === 3");

    $shell->click('[data-path="bestanden"] li:nth-child(2) [data-slot="edit"]');

    expect($shell->script(waitUntil(fileState('bestanden.1.bestand', 'gone'))))->toBeTrue();

    $shell->assertSeeIn('[data-path="bestanden.1.bestand"] [data-slot="file-gone"]', 'Dit bestand staat niet meer in Media.')
        ->assertMissing('[data-path="bestanden.1.bestand"] [data-slot="file-misfit"]')
        ->click('[data-slot="back"]')
        ->click('[data-path="bestanden"] li:nth-child(3) [data-slot="edit"]');

    expect($shell->script(waitUntil(fileState('bestanden.2.bestand', 'gone'))))->toBeTrue();

    $shell->assertSeeIn('[data-path="bestanden.2.bestand"] [data-slot="file-misfit"]', 'Dit bestand past hier niet.')
        ->assertSeeIn('[data-path="bestanden.2.bestand"] [data-slot="file-misfit"]', 'Hier hoort een document.')
        ->assertMissing('[data-path="bestanden.2.bestand"] [data-slot="file-gone"]')
        ->click('[data-slot="back"]')
        ->click('[data-path="bestanden"] li:nth-child(1) [data-slot="edit"]');

    expect($shell->script(waitUntil(fileState('bestanden.0.bestand', 'filled'))))->toBeTrue();

    $shell->assertAttribute('[data-path="bestanden.0.bestand"] [data-slot="file-remove"]', 'aria-label', 'Bestand weghalen')
        ->click('[data-path="bestanden.0.bestand"] [data-slot="file-remove"]');

    expect($shell->script(waitUntil(fileState('bestanden.0.bestand', 'empty'))))->toBeTrue()
        ->and($shell->script('() => document.activeElement?.dataset.slot'))->toBe('file-upload');

    $shell->assertNoJavaScriptErrors();
});

it('shows a document as paper and a video on deep green, also in the dark', function (): void {
    $this->page->update(['strips' => [$this->page->strips[0], storedStrip('downloads', ['bestanden' => [
        ['id' => DOWNLOAD_SCHEMA, 'titel' => null, 'bestand' => $this->schema->id],
    ]], id: 'downloads')]]);

    $shell = visit('/made-cms/pages/'.$this->page->id.'/edit')->inDarkMode();

    expect($shell->script(waitUntil(siteInFrame()."?.querySelector('made-cms-outlines')?.shadowRoot?.querySelectorAll('.omlijning').length === 2")))->toBeTrue();

    $shell->withinFrame('.bewerkframe', fn ($frame) => $frame->click('[data-strip="downloads"] .tab'));
    $shell->click('[data-path="bestanden"] li:nth-child(1) [data-slot="edit"]');

    expect($shell->script(waitUntil(fileState('bestanden.0.bestand', 'filled'))))->toBeTrue()
        ->and($shell->script(<<<'JS'
            () => {
                const colour = (selector, property) => getComputedStyle(document.querySelector(selector))[property];

                return [
                    colour('body', 'backgroundColor'),
                    colour('[data-path="bestanden.0.bestand"] .beeldkaart-voorbeeld', 'backgroundColor'),
                    getComputedStyle(document.querySelector('[data-path="bestanden.0.bestand"] .tegel-icoon-document')).display,
                    getComputedStyle(document.querySelector('[data-path="bestanden.0.bestand"] .tegel-icoon-video')).display,
                ];
            }
            JS))->toBe(['rgb(14, 43, 38)', 'rgb(243, 241, 232)', 'block', 'none']);

    $shell->assertNoJavaScriptErrors();
});
