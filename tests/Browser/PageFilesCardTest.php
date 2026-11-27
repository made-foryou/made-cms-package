<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\SyncMediaUsage;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
    Storage::fake('public');
    useStripTypes();

    // The fake media disk has no address of its own: this serves its files, so a thumbnail loads.
    Route::get('storage/{path}', fn (string $path) => response()->file(Storage::disk('public')->path($path)))->where('path', '.*');

    $this->clubavond = UploadMedia::run(new UploadedMedia(photo(1200, 800, 'jpg', 'Clubavond.jpg'), 'Clubavond.jpg'));
    $this->clubavond->update(['alt' => 'Leden aan de tafels']);
    $this->training = UploadMedia::run(new UploadedMedia(photo(1600, 1067, 'jpg', 'Training dinsdag.jpg'), 'Training dinsdag.jpg'));
    $this->opstelling = Media::factory()->document()->create(['name' => 'opstelling-najaar']);
    $this->page = Page::factory()->published()->at('/teams')
        ->withStrips([
            storedStrip('hero', ['foto' => $this->clubavond->id]),
            storedStrip('cards', ['kaarten' => [['id' => 'k1', 'titel' => 'Dinsdag', 'icoon' => $this->training->id]]]),
            storedStrip('hero', ['foto' => $this->opstelling->id]),
        ])
        ->create(['title' => 'Onze teams', 'slug' => 'teams']);
    SyncMediaUsage::run($this->page);

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('shows the files of the page small, a photo without an alt text with a dot, and opens one in Media chosen', function (): void {
    $card = visit('/made-cms/pages/'.$this->page->id)->resize(1280, 900);

    // The thumbnails load lazily: the card comes into view first, and the photos may take a moment.
    $card->script("() => document.querySelector('#made-cms-bestanden').scrollIntoView()");
    $thumbs = "[...document.querySelectorAll('#made-cms-bestanden img')]";

    expect($card->script(waitUntil("{$thumbs}.length === 2 && {$thumbs}.every((thumb) => thumb.complete && thumb.naturalWidth > 0)")))->toBeTrue()
        ->and($card->script(<<<'JS'
            () => [...document.querySelectorAll('#made-cms-bestanden .paginabestand')].map((file) => ({
                name: file.querySelector('.paginabestand-naam').firstChild.textContent,
                thumb: file.querySelector('img') !== null,
                dot: file.querySelector('.beeldkaart-stip') !== null,
            }))
            JS))->toBe([
            ['name' => 'clubavond.webp', 'thumb' => true, 'dot' => false],
            ['name' => 'training-dinsdag.webp', 'thumb' => true, 'dot' => true],
            ['name' => 'opstelling-najaar.pdf', 'thumb' => false, 'dot' => false],
        ]);

    $card->assertSeeIn('#made-cms-bestanden', 'Eén foto heeft nog geen alt-tekst (de amber stip).')
        ->assertSeeIn('.paginakort-feiten', '2 foto’s, 1 document')
        ->assertNoJavaScriptErrors()
        ->click('#made-cms-bestanden a.paginabestand[href$="?file='.$this->training->id.'"]');

    expect($card->script(waitUntil("location.search === '?file={$this->training->id}' && document.querySelector('[data-slot=\"details-name\"]')?.textContent === 'training-dinsdag.webp'")))->toBeTrue();

    $card->assertNoJavaScriptErrors();
});

it('lays the files on night green in the dark colours of the system, a document as light paper', function (): void {
    $card = visit('/made-cms/pages/'.$this->page->id)->inDarkMode();

    expect($card->script(<<<'JS'
        () => {
            const colour = (kind) => getComputedStyle(document.querySelector(`.paginabestand-beeld[data-kind="${kind}"]`)).backgroundColor;

            return [colour('image'), colour('document'), getComputedStyle(document.querySelector('#made-cms-bestanden')).backgroundColor];
        }
        JS))->toBe(['rgb(14, 43, 38)', 'rgb(243, 241, 232)', 'rgb(27, 61, 54)']);

    $card->assertNoJavaScriptErrors();
});

it('lays a document on paper in the light colours too', function (): void {
    $card = visit('/made-cms/pages/'.$this->page->id);

    expect($card->script("() => getComputedStyle(document.querySelector('.paginabestand-beeld[data-kind=\"document\"]')).backgroundColor"))
        ->toBe('rgb(251, 250, 245)');
});
