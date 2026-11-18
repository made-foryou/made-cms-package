<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Content\Models\Page;

/*
| The picker opened for the photo of an item of a list (the gallery of the workbench, at most 8 photos) chooses several
| photos at once: the first goes into that item, every next one becomes a new item right after it, in the order they
| were chosen, as long as the list takes them.
*/

const FOTO_EEN = '1b4f6c2e-8a3d-4e5f-9b0c-7d2e1f3a4b5c';
const FOTO_TWEE = '2c5a7d3f-9b4e-4f60-8c1d-8e3f2a4b5c6d';

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
    $this->teamfoto = Media::factory()->in($this->teams)->withAlt('Het eerste team')->create(['name' => 'teamfoto', 'created_at' => now()->subDays(3)]);
    $this->training = Media::factory()->in($this->teams)->create(['name' => 'training', 'created_at' => now()->subDays(2)]);
    $this->toernooi = Media::factory()->in($this->teams)->create(['name' => 'toernooi', 'created_at' => now()->subDay()]);
    $this->clubavond = Media::factory()->in($this->nieuws)->create(['name' => 'clubavond', 'created_at' => now()]);

    foreach ([$this->teamfoto, $this->training, $this->toernooi, $this->clubavond] as $media) {
        Storage::disk('public')->put($media->path, (string) file_get_contents(photo(1600, 1067, 'webp')));
        Storage::disk('public')->put((string) $media->thumb_path, (string) file_get_contents(photo(540, 360, 'webp')));
    }

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * A page with a hero and a gallery of these items.
 *
 * @param  list<array<string, mixed>>  $photos
 */
function galleryPage(array $photos): Page
{
    return Page::factory()->published()->at('/fotos')->withStrips([
        storedStrip('hero', ['kop' => 'Foto’s'], id: 'hero'),
        storedStrip('gallery', ['fotos' => $photos], id: 'galerij'),
    ])->create(['title' => 'Foto’s']);
}

/**
 * Opens the gallery in the side panel, the item at this place, and the picker for its photo.
 */
function openGalleryPicker(Page $page, int $item, int $items): mixed
{
    $shell = openStrip($page, 'galerij', "document.querySelectorAll('[data-path=\"fotos\"] [data-slot=\"rows\"] > li').length === {$items}");

    $shell->click('[data-path="fotos"] [data-slot="rows"] > li:nth-child('.($item + 1).') [data-slot="edit"]')
        ->click('[data-path="fotos.'.$item.'.foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil("document.querySelector('[data-media-picker]').open && ! document.querySelector('[data-media]').hasAttribute('data-loading') && document.querySelectorAll('[data-media-picker] .tegel').length > 0")))->toBeTrue();

    return $shell;
}

function galleryTile(Media $media): string
{
    return '[data-media-picker] .tegel[data-id="'.$media->id.'"]';
}

/**
 * A script for $page->script() that clicks a tile of the picker with Shift, Cmd or Ctrl held.
 */
function clickGalleryTileWith(Media $media, string $key): string
{
    return "() => { document.querySelector('".galleryTile($media)."').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, {$key}: true })); return true; }";
}

/**
 * A script for $page->script(): the ids of the tiles the picker shows selected, in the order of the number on them.
 */
function galleryNumbers(): string
{
    return "() => [...document.querySelectorAll('[data-media-picker] .tegel[data-number]')].sort((a, b) => a.dataset.number - b.dataset.number).map((tile) => Number(tile.dataset.id))";
}

/**
 * A script for $page->script() that hands files to the field of several photos of the picker, as a drop on it would.
 *
 * @param  array<string, string>  $files  the path of every file by the name it gets
 */
function takeInGalleryPicker(array $files): string
{
    $specs = json_encode(array_map(static fn (string $name, string $path): array => [
        'name' => $name,
        'type' => (string) mime_content_type($path),
        'data' => base64_encode((string) file_get_contents($path)),
    ], array_keys($files), $files), JSON_THROW_ON_ERROR);

    return <<<JS
        () => {
            const files = {$specs}.map((spec) => new File(
                [Uint8Array.from(atob(spec.data), (character) => character.charCodeAt(0))],
                spec.name,
                { type: spec.type },
            ));

            document.querySelector('[data-media-picker] [data-picker-upload=many] [data-upload]')
                .dispatchEvent(new CustomEvent('made-cms:upload-take', { detail: { files } }));

            return files.length;
        }
        JS;
}

/**
 * A script for $page->script(): the text of a slot, '' when it is hidden.
 */
function pickerSays(string $slot): string
{
    return "() => { const element = document.querySelector('[data-slot=\"{$slot}\"]'); return element?.checkVisibility() ? element.textContent.trim() : ''; }";
}

/**
 * The photo of every item of the gallery as it is stored now, in order.
 *
 * @return list<int|null>
 */
function galleryPhotos(Page $page): array
{
    return array_map(static fn (array $item): ?int => $item['foto'] ?? null, draftOf($page, 'galerij')['values']['fotos'] ?? []);
}

it('fills a gallery with three photos at once: the first in the open item, the rest right after it, in the order chosen', function (): void {
    $page = galleryPage([
        ['id' => FOTO_EEN, 'foto' => null, 'bijschrift' => 'Eerste'],
        ['id' => FOTO_TWEE, 'foto' => null, 'bijschrift' => 'Laatste'],
    ]);
    $shell = openGalleryPicker($page, 0, 2);

    // Before: one photo at a time, with the button and how to choose more.
    expect($shell->script(pickerSays('media-picker-status')))->toBe('')
        ->and($shell->script(pickerSays('media-picker-hint')))->toBe('Cmd- of Ctrl-klik voegt een foto toe, Shift-klik kiest een reeks vanaf je keuze. Net als in de bibliotheek.')
        ->and($shell->script(pickerSays('media-picker-full')))->toBe('');

    $shell->assertAttribute('[data-slot="media-picker-many"]', 'aria-pressed', 'false');

    // Cmd-click starts selecting; the order of clicking is the order of the items.
    $shell->script(clickGalleryTileWith($this->toernooi, 'metaKey'));
    $shell->click(galleryTile($this->teamfoto))
        ->click(galleryTile($this->clubavond));

    expect($shell->script(galleryNumbers()))->toBe([$this->toernooi->id, $this->teamfoto->id, $this->clubavond->id])
        ->and($shell->script(pickerSays('media-picker-status')))->toBe('3 gekozen · er passen er nog 4 bij')
        ->and($shell->script(pickerSays('media-picker-hint')))->toBe('Klik, Enter of spatie zet een foto aan of uit. Shift-klik kiest een reeks. Met Escape sluit je zonder wijzigingen.')
        ->and($shell->script(pickerSays('media-picker-chosen')))->toBe('3 gekozen')
        ->and($shell->script(pickerSays('media-picker-where')))->toBe('Nummer 1 komt in Foto 1, de rest in nieuwe items erna.')
        ->and($shell->script("() => [...document.querySelectorAll('[data-slot=\"media-picker-chips\"] li')].map((chip) => chip.title + ':' + chip.querySelector('[data-slot=\"media-picker-chip-number\"]').textContent)"))->toBe(['toernooi:1', 'teamfoto:2', 'clubavond:3'])
        // A folder says how many of its photos are chosen.
        ->and($shell->script("() => [...document.querySelectorAll('[data-media-picker] .kmap')].map((folder) => folder.querySelector('.kmap-naam').textContent.trim() + ':' + (folder.querySelector('.kmap-gekozen')?.checkVisibility() ? folder.querySelector('.kmap-gekozen').textContent : ''))"))->toBe(['Alle bestanden:', 'Nieuws:1', 'Teams:2']);

    $shell->assertAttribute('[data-slot="media-picker-many"]', 'aria-pressed', 'true')
        ->assertAttribute(galleryTile($this->toernooi), 'aria-label', 'toernooi, nummer 1')
        ->assertSeeIn('[data-slot="media-picker-use"]', 'Deze 3 gebruiken');

    // The cross of a chip takes its photo out; the numbers follow.
    $shell->click('[data-slot="media-picker-chips"] li:nth-child(2) [data-slot="media-picker-chip-remove"]');

    expect($shell->script(galleryNumbers()))->toBe([$this->toernooi->id, $this->clubavond->id])
        ->and($shell->script('() => document.activeElement?.closest("li")?.title'))->toBe('clubavond');

    $shell->click(galleryTile($this->teamfoto));

    expect($shell->script(galleryNumbers()))->toBe([$this->toernooi->id, $this->clubavond->id, $this->teamfoto->id]);

    $shell->assertAttribute(galleryTile($this->training), 'aria-pressed', 'false')
        ->assertAttribute('[data-slot="media-picker-use"]', 'aria-disabled', 'false')
        ->click('[data-slot="media-picker-use"]');

    expect($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and(galleryPhotos($page))->toBe([$this->toernooi->id, $this->clubavond->id, $this->teamfoto->id, null])
        ->and(array_column(draftOf($page, 'galerij')['values']['fotos'], 'bijschrift'))->toBe(['Eerste', null, null, 'Laatste'])
        ->and(draftOf($page, 'galerij')['values']['fotos'][0]['id'])->toBe(FOTO_EEN);

    // The panel stays in the item, says where it is now, and the focus is back on "Kiezen uit media".
    $shell->assertSeeIn('[data-slot="item-position"]', 'Item 1 van 4');

    expect($shell->script(pickerSays('upload-status-text')))->toBe('2 foto’s toegevoegd na Foto 1.')
        ->and($shell->script(pickerSays('upload-status-undo')))->toBe('Ongedaan maken')
        ->and($shell->script('() => document.activeElement?.dataset.slot'))->toBe('image-pick')
        ->and($shell->script(waitUntil("document.querySelector('.bewerkframe').contentDocument?.querySelectorAll('[data-cms-items=\"fotos\"] img').length === 3")))->toBeTrue();

    // In the list the new items say they are new.
    $shell->click('[data-slot="back"]');

    expect($shell->script("() => [...document.querySelectorAll('[data-path=\"fotos\"] [data-slot=\"rows\"] > li')].map((row) => row.querySelector('[data-slot=\"fresh\"]').checkVisibility())"))->toBe([false, true, true, false]);

    $shell->assertNoJavaScriptErrors();
});

it('selects a range with Shift and takes a photo out with Ctrl, keeping the order of the rest', function (): void {
    $page = galleryPage([['id' => FOTO_EEN, 'foto' => null, 'bijschrift' => null]]);
    $shell = openGalleryPicker($page, 0, 1);

    // Newest first: clubavond, toernooi, training, teamfoto. A plain click chooses, Shift-click selects the range.
    $shell->click(galleryTile($this->clubavond));
    $shell->script(clickGalleryTileWith($this->training, 'shiftKey'));

    expect($shell->script(galleryNumbers()))->toBe([$this->clubavond->id, $this->toernooi->id, $this->training->id]);

    $shell->script(clickGalleryTileWith($this->toernooi, 'ctrlKey'));

    expect($shell->script(galleryNumbers()))->toBe([$this->clubavond->id, $this->training->id]);

    // A range upwards counts upwards: from training back to the photo above it.
    $shell->script(clickGalleryTileWith($this->toernooi, 'shiftKey'));

    expect($shell->script(galleryNumbers()))->toBe([$this->clubavond->id, $this->training->id, $this->toernooi->id]);

    $shell->click('[data-slot="media-picker-use"]');

    expect($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and(galleryPhotos($page))->toBe([$this->clubavond->id, $this->training->id, $this->toernooi->id]);

    $shell->assertNoJavaScriptErrors();
});

it('keeps the selection in another folder, with the photo the item has as the first', function (): void {
    $page = galleryPage([['id' => FOTO_EEN, 'foto' => $this->clubavond->id, 'bijschrift' => null]]);
    $shell = openGalleryPicker($page, 0, 1);

    // The item has clubavond, in Nieuws; selecting in Teams starts with it.
    $shell->click('[data-media-picker] .kmap:has-text("Teams")');

    expect($shell->script(waitUntil("! document.querySelector('[data-media]').hasAttribute('data-loading') && ! document.querySelector('".galleryTile($this->clubavond)."')")))->toBeTrue();

    $shell->script(clickGalleryTileWith($this->training, 'metaKey'));
    $shell->click('[data-media-picker] .kmap:has-text("Alle bestanden")');

    expect($shell->script(waitUntil("document.querySelector('".galleryTile($this->clubavond)."')?.dataset.number === '1'")))->toBeTrue()
        ->and($shell->script(galleryNumbers()))->toBe([$this->clubavond->id, $this->training->id]);

    $shell->click('[data-slot="media-picker-use"]');

    expect($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and(galleryPhotos($page))->toBe([$this->clubavond->id, $this->training->id]);

    $shell->assertNoJavaScriptErrors();
});

it('takes no more photos than the list holds', function (): void {
    // Six of eight: from the last item three photos fit, this one and two more.
    $page = galleryPage(array_map(static fn (int $n): array => ['foto' => null, 'bijschrift' => 'Foto '.$n], range(1, 6)));
    $shell = openGalleryPicker($page, 5, 6);

    $shell->click(galleryTile($this->clubavond));
    $shell->script(clickGalleryTileWith($this->teamfoto, 'shiftKey'));

    // The range from clubavond to teamfoto holds four photos; the fourth is left out.
    expect($shell->script(galleryNumbers()))->toBe([$this->clubavond->id, $this->toernooi->id, $this->training->id])->and($shell->script("() => document.querySelector('".galleryTile($this->teamfoto)."').getAttribute('aria-pressed')"))->toBe('false');

    // A tile beyond the limit is off: a click on it changes nothing.
    $shell->script("() => document.querySelector('".galleryTile($this->teamfoto)."').click()");

    expect($shell->script(galleryNumbers()))->toHaveCount(3)
        ->and($shell->script(pickerSays('media-picker-status')))->toBe('3 gekozen · dit is het maximum')
        ->and($shell->script("() => [...document.querySelectorAll('[data-slot=\"media-picker-notices\"] .melding-tekst')].map((text) => text.textContent)"))->toBe(['Er passen er niet meer bij: Fotogalerij heeft maximaal 8 foto’s.']);

    $shell->assertAttribute(galleryTile($this->teamfoto), 'aria-disabled', 'true');

    $shell->click('[data-slot="media-picker-use"]');

    expect($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and(galleryPhotos($page))->toHaveCount(8)
        ->and(array_slice(galleryPhotos($page), 5))->toBe([$this->clubavond->id, $this->toernooi->id, $this->training->id]);

    $shell->assertNoJavaScriptErrors();
});

it('chooses one photo for a list that is full, and outside a list', function (): void {
    $page = galleryPage(array_map(static fn (int $n): array => ['foto' => null, 'bijschrift' => 'Foto '.$n], range(1, 8)));
    $shell = openGalleryPicker($page, 0, 8);

    // Nothing selects: Cmd-click chooses the photo like a plain click, and the field of one photo is in sight.
    $shell->script(clickGalleryTileWith($this->training, 'metaKey'));

    expect($shell->script(galleryNumbers()))->toBe([])
        ->and($shell->script("() => [...document.querySelectorAll('[data-media-picker] [data-picker-upload]')].map((wrapper) => wrapper.dataset.pickerUpload + ':' + wrapper.hidden)"))->toBe(['one:false', 'many:true'])
        ->and($shell->script(pickerSays('media-picker-full')))->toBe('Fotogalerij is vol: 8 van 8')
        ->and($shell->script(pickerSays('media-picker-hint')))->toBe('Fotogalerij is vol (8 van 8), dus je kiest één foto voor Foto 1.');

    $shell->assertAttribute('[data-slot="media-picker-many"]', 'aria-disabled', 'true')
        ->script("() => document.querySelector('[data-slot=\"media-picker-many\"]').click()");

    expect($shell->script(galleryNumbers()))->toBe([]);

    $shell->assertAttribute(galleryTile($this->training), 'aria-pressed', 'true')
        ->click('[data-slot="media-picker-use"]');

    expect($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and(galleryPhotos($page)[0])->toBe($this->training->id)
        ->and(galleryPhotos($page))->toHaveCount(8);

    $shell->assertNoJavaScriptErrors();
});

it('takes back the whole choice with one step of "Ongedaan maken"', function (): void {
    $page = galleryPage([['id' => FOTO_EEN, 'foto' => $this->teamfoto->id, 'bijschrift' => null]]);
    $shell = openGalleryPicker($page, 0, 1);

    // The photo the item has is the first; two more follow it.
    $shell->script(clickGalleryTileWith($this->clubavond, 'metaKey'));
    $shell->click(galleryTile($this->toernooi))
        ->click('[data-slot="media-picker-use"]');

    expect($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and(galleryPhotos($page))->toBe([$this->teamfoto->id, $this->clubavond->id, $this->toernooi->id]);

    // "Ongedaan maken" on the line at the foot of the panel.
    $shell->click('[data-slot="upload-status-undo"]');

    // One step back takes the page back to what visitors see.
    expect($shell->script(waitUntil("document.querySelectorAll('[data-path=\"fotos\"] [data-slot=\"rows\"] > li').length === 1 && document.querySelector('[data-slot=\"bar-state\"]').textContent === 'Geen wijzigingen'")))->toBeTrue()
        ->and($shell->script("() => document.querySelector('[data-slot=\"undo\"]').disabled"))->toBeTrue()
        ->and(galleryPhotos($page))->toBe([$this->teamfoto->id]);

    $shell->assertNoJavaScriptErrors();
});

it('lets several photos uploaded at once join the selection as the next items', function (): void {
    $page = galleryPage([['id' => FOTO_EEN, 'foto' => null, 'bijschrift' => null]]);
    $shell = openGalleryPicker($page, 0, 1);

    expect($shell->script("() => [...document.querySelectorAll('[data-media-picker] [data-picker-upload]')].map((wrapper) => wrapper.dataset.pickerUpload + ':' + wrapper.hidden)"))->toBe(['one:true', 'many:false']);

    // Svgs: the crop step passes them by, so the field asks their alt texts itself.
    $shell->script(takeInGalleryPicker([
        'Logo een.svg' => mediaFixture('logo.svg'),
        'Logo twee.svg' => mediaFixture('logo.svg'),
    ]));

    expect($shell->script(waitUntil("document.querySelector('[data-media-picker] [data-picker-upload=many] [data-upload]').dataset.moment === 'done'")))->toBeTrue();

    $logos = Media::query()->whereIn('name', ['logo-een', 'logo-twee'])->orderBy('id')->pluck('id')->all();

    expect($logos)->toHaveCount(2)
        ->and($shell->script(waitUntil('('.galleryNumbers().')().length === 2')))->toBeTrue()
        ->and($shell->script(galleryNumbers()))->toBe([$logos[0], $logos[1]])
        ->and($shell->script("() => [...document.querySelectorAll('[data-slot=\"media-picker-notices\"] .melding-tekst')].map((text) => text.textContent)"))->toBe(['Meerdere kiezen staat aan: je sleepte meer dan één foto. Elke foto die binnen is, krijgt het volgende nummer.'])
        ->and($shell->script(waitUntil("document.querySelector('".galleryTile(Media::query()->findOrFail($logos[0]))." [data-slot=\"media-tile-new\"]')?.checkVisibility() === true")))->toBeTrue();

    $shell->assertSeeIn(galleryTile(Media::query()->findOrFail($logos[0])).' [data-slot="media-tile-meta"]', 'zojuist · ');

    $shell->click('[data-slot="media-picker-use"]');

    expect($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and(galleryPhotos($page))->toBe($logos);

    $shell->assertNoJavaScriptErrors();
});

it('says which photos uploaded at once no longer fit in the list, and leaves them in Media', function (): void {
    // Seven of eight: from the last item two photos fit.
    $page = galleryPage(array_map(static fn (int $n): array => ['foto' => null, 'bijschrift' => 'Foto '.$n], range(1, 7)));
    $shell = openGalleryPicker($page, 6, 7);

    $shell->script(takeInGalleryPicker([
        'Logo een.svg' => mediaFixture('logo.svg'),
        'Logo twee.svg' => mediaFixture('logo.svg'),
        'Logo drie.svg' => mediaFixture('logo.svg'),
    ]));

    expect($shell->script(waitUntil("document.querySelector('[data-media-picker] [data-picker-upload=many] [data-upload]').dataset.moment === 'done'")))->toBeTrue()
        ->and(Media::query()->where('name', 'like', 'logo-%')->count())->toBe(3)
        ->and($shell->script(waitUntil("[...document.querySelectorAll('[data-slot=\"media-picker-notices\"] .melding-tekst')].some((text) => text.textContent === '1 foto staat in Media; hij past niet meer in Fotogalerij.')")))->toBeTrue()
        ->and($shell->script(waitUntil('('.galleryNumbers().')().length === 2')))->toBeTrue();

    $shell->assertNoJavaScriptErrors();
});

it('runs photos uploaded at once through the crop step one after the other, and hands them on as the step stored them', function (): void {
    $page = galleryPage([['id' => FOTO_EEN, 'foto' => null, 'bijschrift' => null]]);
    $shell = openGalleryPicker($page, 0, 1);

    $shell->script(takeInGalleryPicker([
        'Kampioenen.jpg' => photo(1600, 1067, 'jpg'),
        'Huldiging.jpg' => photo(1600, 1067, 'jpg'),
    ]));

    foreach (['De kampioenen', 'De huldiging'] as $alt) {
        expect($shell->script(waitUntil("document.querySelector('[data-media-crop]').open && ! document.querySelector('[data-slot=\"crop-save\"]').disabled && document.querySelector('[data-slot=\"crop-alt\"]').value === ''")))->toBeTrue();

        $shell->type('[data-slot="crop-alt"]', $alt)
            ->click('[data-slot="crop-save"]');
    }

    expect($shell->script(waitUntil("! document.querySelector('[data-media-crop]').open")))->toBeTrue();

    $photos = Media::query()->whereIn('name', ['kampioenen', 'huldiging'])->orderBy('id')->get();

    expect($photos->pluck('alt')->all())->toBe(['De kampioenen', 'De huldiging'])
        ->and($shell->script(waitUntil('('.galleryNumbers().')().length === 2')))->toBeTrue();

    $shell->click('[data-slot="media-picker-use"]');

    // The item holds the first photo with the alt text of the crop step, not the form it had when it came in.
    expect($shell->script(waitUntil(savedAsDraft())))->toBeTrue()
        ->and(galleryPhotos($page))->toBe($photos->pluck('id')->all());

    $shell->assertSeeIn('[data-path="fotos.0.foto"] [data-slot="image-alt"]', 'Alt: De kampioenen')
        ->assertNoJavaScriptErrors();
});

it('sends the field of several photos into the folder the editor opened in an earlier picker', function (): void {
    $page = Page::factory()->published()->at('/fotos')->withStrips([
        storedStrip('hero', ['kop' => 'Foto’s'], id: 'hero'),
        storedStrip('gallery', ['fotos' => [['id' => FOTO_EEN, 'foto' => null, 'bijschrift' => null]]], id: 'galerij'),
    ])->create(['title' => 'Foto’s']);

    // The hero opens the picker with the field of one photo; the editor opens Nieuws there.
    $shell = openStrip($page, 'hero', "document.querySelector('[data-path=\"foto\"] [data-slot=\"image-pick\"]') !== null");
    $shell->click('[data-path="foto"] [data-slot="image-pick"]');

    expect($shell->script(waitUntil("document.querySelector('[data-media-picker]').open")))->toBeTrue()
        // Outside a list there is no button: the hint says where several photos are chosen.
        ->and($shell->script(pickerSays('media-picker-many')))->toBe('')
        ->and($shell->script(pickerSays('media-picker-hint')))->toBe('Meerdere foto’s tegelijk kiezen kan alleen bij een lijst, zoals de foto’s in een fotogalerij. Open de kiezer dan vanuit een item van die lijst.');

    $shell->click('[data-media-picker] .kmap:has-text("Nieuws")');

    expect($shell->script(waitUntil("document.querySelector('[data-media-picker] [data-picker-upload=many] [data-slot=\"upload-rules\"]')?.textContent.includes('de map Nieuws')")))->toBeTrue();

    $shell->keys('[data-slot="media-search"]', 'Escape');
    $shell->assertNoJavaScriptErrors();
});
