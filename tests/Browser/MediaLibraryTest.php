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

beforeEach(function (): void {
    config(['app.url' => 'http://localhost']);
    Storage::fake('public');

    // The fake media disk has no address of its own: this serves its files. It goes before the site, whose pages take
    // every address that is left.
    Route::get('storage/{path}', fn (string $path) => Storage::disk('public')->exists($path)
        ? response()->file(Storage::disk('public')->path($path))
        : abort(404))->where('path', '.*');
    useWorkbenchSite();
    useStripTypes();

    $this->teams = MediaFolder::factory()->create(['name' => 'Teams']);
    $this->nieuws = MediaFolder::factory()->create(['name' => 'Nieuws']);
    $this->teamfoto = Media::factory()->in($this->teams)->withAlt('Het eerste team voor de tafels')->create([
        'name' => 'teamfoto-merwestad-1',
        'path' => 'media/01k9/teamfoto-merwestad-1.webp',
        'thumb_path' => 'media/01k9/thumb-klein.webp',
        'width' => 1600,
        'height' => 1067,
        'size' => 412_000,
        'created_at' => now()->subDays(3),
    ]);
    $this->training = Media::factory()->in($this->teams)->create([
        'name' => 'training-dinsdag',
        'path' => 'media/01ka/training-dinsdag.webp',
        'thumb_path' => 'media/01ka/thumb-training.webp',
        'created_at' => now()->subDays(2),
    ]);
    $this->clubavond = Media::factory()->in($this->nieuws)->create([
        'name' => 'clubavond',
        'path' => 'media/01kb/clubavond.webp',
        'thumb_path' => 'media/01kb/thumb-clubavond.webp',
        'created_at' => now()->subDay(),
    ]);
    $this->schema = Media::factory()->in($this->teams)->document()->withAlt('Wedstrijdschema najaar (pdf)')->create(['name' => 'wedstrijdschema', 'created_at' => now()]);

    foreach ([$this->teamfoto, $this->training, $this->clubavond] as $media) {
        Storage::disk('public')->put($media->path, (string) file_get_contents(photo(1600, 1067, 'webp')));
        Storage::disk('public')->put((string) $media->thumb_path, (string) file_get_contents(photo(540, 360, 'webp')));
    }

    $this->page = Page::factory()->published()->at('/teams')->withStrips([
        storedStrip('hero', ['kop' => 'Onze teams', 'foto' => $this->teamfoto->id], id: 'hero'),
    ])->create(['title' => 'Onze teams']);
    SyncMediaUsage::run($this->page);

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * A JavaScript condition: the list of the library is loaded and shows these files, in this order.
 *
 * @param  list<string>  $names
 */
function libraryShows(array $names): string
{
    $quoted = json_encode($names, JSON_THROW_ON_ERROR);

    return "! document.querySelector('[data-media]').hasAttribute('data-loading') && JSON.stringify([...document.querySelectorAll('[data-media] .tegel [data-slot=\"media-tile-name\"]')].map((name) => name.textContent)) === JSON.stringify({$quoted})";
}

function libraryTile(Media $media): string
{
    return '[data-media] .tegel[data-id="'.$media->id.'"]';
}

/**
 * A script for $page->script(): every filter with its text and whether it is pressed.
 */
function libraryFilters(): string
{
    return "() => [...document.querySelectorAll('[data-slot=\"media-kinds\"] .filter')].map((button) => [button.textContent.replace(/\\s+/g, ' ').trim(), button.getAttribute('aria-pressed')])";
}

/**
 * A script for $page->script(): every folder chip with its number and whether it is open.
 */
function libraryFolders(): string
{
    return "() => [...document.querySelectorAll('[data-media] li[data-folder-item] .kmap')].map((folder) => [folder.querySelector('.kmap-naam').textContent.trim(), folder.querySelector('.kmap-aantal').textContent, folder.getAttribute('aria-pressed')])";
}

/**
 * A JavaScript condition: the side column shows a file and has read where it is used.
 */
function libraryDetails(string $name): string
{
    return "document.querySelector('[data-slot=\"details-name\"]').textContent === '{$name}' && document.querySelector('[data-slot=\"details-loading\"]').hidden";
}

/**
 * A script for $page->script() that lets files from the computer hang above the library and, with $drop, lets them go.
 *
 * @param  list<array{name: string, type: string, path: string}>  $files
 */
function dropOnLibrary(array $files, bool $drop, string $target = '[data-slot="media-files"]'): string
{
    $specs = json_encode(array_map(static fn (array $file): array => [
        'name' => $file['name'],
        'type' => $file['type'],
        'data' => base64_encode((string) file_get_contents($file['path'])),
    ], $files), JSON_THROW_ON_ERROR);
    $events = json_encode($drop ? ['dragenter', 'dragover', 'drop'] : ['dragenter', 'dragover'], JSON_THROW_ON_ERROR);

    return <<<JS
        () => {
            const transfer = new DataTransfer();

            for (const spec of {$specs}) {
                transfer.items.add(new File([Uint8Array.from(atob(spec.data), (c) => c.charCodeAt(0))], spec.name, { type: spec.type }));
            }

            for (const type of {$events}) {
                document.querySelector('{$target}').dispatchEvent(new DragEvent(type, { bubbles: true, cancelable: true, dataTransfer: transfer }));
            }

            return transfer.files.length;
        }
        JS;
}

it('opens with the first page of files and the folders, without asking the server for them', function (): void {
    $page = visit('/made-cms/media')->resize(1280, 1100);

    expect($page->script(waitUntil(libraryShows(['wedstrijdschema', 'clubavond', 'training-dinsdag', 'teamfoto-merwestad-1']))))->toBeTrue()
        ->and($page->script("() => performance.getEntriesByType('resource').filter((entry) => /\\/made-cms\\/media\\/(files|folders)/.test(entry.name)).length"))->toBe(0)
        ->and($page->script(libraryFilters()))->toBe([
            ['Alle 4', 'true'],
            ['Afbeeldingen 3', 'false'],
            ['Documenten 1', 'false'],
            ['Video’s 0', 'false'],
            ['2 zonder alt-tekst', 'false'],
        ])
        ->and($page->script(libraryFolders()))->toBe([['Nieuws', '1', 'false'], ['Teams', '3', 'false']])
        // The library keeps the amber dot on a chosen tile and knows a document by its icon.
        ->and($page->script("() => [...document.querySelectorAll('[data-media] .tegel')].map((tile) => [tile.dataset.kind, tile.querySelector('[data-slot=\"media-tile-no-alt\"]').hidden])"))
        ->toBe([['document', true], ['image', false], ['image', false], ['image', true]]);

    $page->assertSeeIn('[data-slot="media-trail-here"]', 'Alle bestanden')
        ->assertMissing('[data-slot="media-trail-all"]')
        ->assertSeeIn('[data-slot="media-status"]', '4 bestanden')
        ->assertAttribute(libraryTile($this->training), 'aria-label', 'training-dinsdag.webp, afbeelding, nog geen alt-tekst')
        ->assertAttribute(libraryTile($this->schema), 'aria-label', 'wedstrijdschema.pdf, document')
        ->assertSeeIn('[data-slot="media-note"]', 'Sleep bestanden van je computer hierheen om ze te uploaden. Een amber stip betekent: nog geen alt-tekst.')
        ->assertSeeIn('[data-slot="details-nothing"]', 'Kies een bestand om te zien waar het staat en zijn alt-tekst te zetten.')
        ->assertMissing('[data-slot="media-upload"]')
        ->assertMissing('[data-slot="media-start"]')
        ->screenshot(filename: 'media-library-light')
        ->assertNoJavaScriptErrors();
});

it('filters by kind or by the images without an alt text, one at a time, and keeps the filter in its address', function (): void {
    $page = visit('/made-cms/media');

    $page->click('[data-slot="media-kinds"] .filter[data-kind="document"]');

    expect($page->script(waitUntil(libraryShows(['wedstrijdschema']))))->toBeTrue()
        ->and($page->script(waitUntil("document.querySelector('[data-media] li[data-folder-item] .kmap-aantal')?.textContent === '0'")))->toBeTrue()
        ->and($page->script(libraryFolders()))->toBe([['Nieuws', '0', 'false'], ['Teams', '1', 'false']])
        ->and($page->script('() => location.search'))->toBe('?kind=document');

    $page->assertSeeIn('[data-slot="media-status"]', '1 document')
        ->click('[data-slot="media-without-alt"]');

    expect($page->script(waitUntil(libraryShows(['clubavond', 'training-dinsdag']))))->toBeTrue()
        ->and(array_column($page->script(libraryFilters()), 1))->toBe(['false', 'false', 'false', 'false', 'true'])
        ->and($page->script('() => location.search'))->toBe('?without_alt=1');

    $page->assertSeeIn('[data-slot="media-status"]', '2 afbeeldingen zonder alt-tekst')
        // Pressing it again keeps it: one filter is always pressed.
        ->click('[data-slot="media-without-alt"]')
        ->assertAttribute('[data-slot="media-without-alt"]', 'aria-pressed', 'true')
        ->click('[data-slot="media-kinds"] .filter[data-kind=""]');

    expect($page->script(waitUntil(libraryShows(['wedstrijdschema', 'clubavond', 'training-dinsdag', 'teamfoto-merwestad-1']))))->toBeTrue()
        ->and($page->script('() => location.search'))->toBe('');

    // A filter that shows nothing in a folder says so, and leads back to every file.
    $page = visit('/made-cms/media?folder='.$this->nieuws->id.'&kind=document');

    expect($page->script(waitUntil(libraryShows([]))))->toBeTrue();

    $page->assertSeeIn('[data-slot="media-empty-title"]', 'Geen documenten in Nieuws')
        ->assertSeeIn('[data-slot="media-empty-text"]', 'Er staan wel andere bestanden in deze map.')
        ->assertMissing('[data-slot="media-note"]')
        ->click('[data-slot="media-empty-link"]');

    expect($page->script(waitUntil(libraryShows(['clubavond']))))->toBeTrue();

    $page->assertNoJavaScriptErrors();
});

it('searches by name, alt text and the title of a page, and says when it finds nothing', function (): void {
    $page = visit('/made-cms/media');

    $page->type('[data-slot="media-search"]', 'onze teams');

    expect($page->script(waitUntil(libraryShows(['teamfoto-merwestad-1']))))->toBeTrue()
        ->and($page->script(waitUntil("document.querySelector('[data-media] li[data-folder-item] .kmap-aantal')?.textContent === '0'")))->toBeTrue()
        ->and($page->script(libraryFolders()))->toBe([['Nieuws', '0', 'false'], ['Teams', '1', 'false']])
        ->and($page->script('() => new URLSearchParams(location.search).get("q")'))->toBe('onze teams');

    $page->assertSeeIn('[data-slot="media-status"]', '1 bestand voor “onze teams”')
        ->type('[data-slot="media-search"]', 'kerstdiner');

    expect($page->script(waitUntil(libraryShows([]))))->toBeTrue();

    $page->assertSeeIn('[data-slot="media-empty-title"]', 'Niets gevonden voor “kerstdiner”')
        ->assertSeeIn('[data-slot="media-empty-text"]', 'Gezocht in de naam, de alt-tekst en de pagina’s waar een bestand op staat.')
        ->assertSeeIn('[data-slot="media-empty-link"]', 'Zoekopdracht wissen')
        ->click('[data-slot="media-empty-link"]');

    expect($page->script(waitUntil(libraryShows(['wedstrijdschema', 'clubavond', 'training-dinsdag', 'teamfoto-merwestad-1']))))->toBeTrue();

    $page->assertValue('[data-slot="media-search"]', '')
        ->assertNoJavaScriptErrors();
});

it('opens a folder from its chip, says where it is, and goes back to all files', function (): void {
    MediaFolder::factory()->create(['name' => 'Jeugd']);
    $page = visit('/made-cms/media');

    $page->click('[data-media] .kmap[data-folder="'.$this->teams->id.'"]');

    expect($page->script(waitUntil(libraryShows(['wedstrijdschema', 'training-dinsdag', 'teamfoto-merwestad-1']))))->toBeTrue()
        ->and($page->script('() => location.search'))->toBe('?folder='.$this->teams->id);

    $page->assertVisible('[data-slot="media-trail-all"]')
        ->assertSeeIn('[data-slot="media-trail-here"]', 'Teams')
        ->assertSeeIn('[data-slot="media-status"]', '3 bestanden')
        ->assertAttribute('[data-media] .kmap[data-folder="'.$this->teams->id.'"]', 'aria-pressed', 'true')
        ->assertSeeIn('[data-slot="media-note"]', 'Sleep bestanden van je computer hierheen om ze in deze map te zetten.')
        ->click('[data-slot="media-trail-all"]');

    expect($page->script(waitUntil(libraryShows(['wedstrijdschema', 'clubavond', 'training-dinsdag', 'teamfoto-merwestad-1']))))->toBeTrue();

    // A chip opens its folder, and once more all files again.
    $page->click('[data-media] .kmap[data-folder="'.$this->nieuws->id.'"]');

    expect($page->script(waitUntil(libraryShows(['clubavond']))))->toBeTrue();

    $page->click('[data-media] .kmap[data-folder="'.$this->nieuws->id.'"]');

    expect($page->script(waitUntil(libraryShows(['wedstrijdschema', 'clubavond', 'training-dinsdag', 'teamfoto-merwestad-1']))))->toBeTrue();

    $page->click('[data-media] .kmap:not([data-folder="'.$this->teams->id.'"]):not([data-folder="'.$this->nieuws->id.'"]):not([data-folder=""])');

    expect($page->script(waitUntil(libraryShows([]))))->toBeTrue();

    $page->assertSeeIn('[data-slot="media-empty-title"]', 'Deze map is nog leeg')
        ->assertSeeIn('[data-slot="media-empty-text"]', 'Ze komen in de map Jeugd.')
        ->assertVisible('[data-slot="media-empty-upload"]')
        ->assertNoJavaScriptErrors();
});

it('shows the files as rows of a list and keeps that view after a reload', function (): void {
    $page = visit('/made-cms/media')->resize(1280, 1100);

    $page->click('[data-slot="media-view-list"]')
        ->assertAttribute('[data-media]', 'data-view', 'list')
        ->assertAttribute('[data-slot="media-view-list"]', 'aria-pressed', 'true')
        ->assertVisible('.mediabrowser-lijstkop');

    expect($page->script("() => [...document.querySelectorAll('[data-media] .tegel')].map((row) => ['kind', 'folder', 'alt'].map((part) => row.querySelector('[data-slot=\"media-tile-' + part + '\"]').textContent.trim()))"))->toBe([
        ['Document', 'Teams', 'Wedstrijdschema najaar (pdf)'],
        ['Afbeelding', 'Nieuws', 'nog leeg'],
        ['Afbeelding', 'Teams', 'nog leeg'],
        ['Afbeelding', 'Teams', 'Het eerste team voor de tafels'],
    ])->and($page->script("() => document.querySelector('".libraryTile($this->teamfoto)." [data-slot=\"media-tile-size\"]').textContent"))->toBe('1600 × 1067 · 412 kB')
        ->and($page->script("() => document.querySelector('[data-media] .tegel [data-slot=\"media-tile-folder\"]').checkVisibility()"))->toBeTrue();

    $page->screenshot(filename: 'media-library-list');

    // After a reload, and in a folder, whose name the trail gives, so the column of the folder leaves the rows.
    $page->navigate('/made-cms/media?folder='.$this->teams->id)
        ->assertAttribute('[data-media]', 'data-view', 'list');

    expect($page->script("() => document.querySelector('[data-media] .tegel [data-slot=\"media-tile-folder\"]').checkVisibility()"))->toBeFalse();

    $page->assertNoJavaScriptErrors();
});

it('shows the chosen file in the side column with the pages it is used on', function (): void {
    $page = visit('/made-cms/media');

    $page->click(libraryTile($this->teamfoto));

    expect($page->script(waitUntil(libraryDetails('teamfoto-merwestad-1.webp'))))->toBeTrue()
        ->and($page->script('() => new URLSearchParams(location.search).get("file")'))->toBe((string) $this->teamfoto->id);

    $page->assertAttribute(libraryTile($this->teamfoto), 'aria-pressed', 'true')
        ->assertSeeIn('[data-slot="details-line"]', 'Afbeelding · webp · 1600 × 1067 · 412 kB · map Teams')
        ->assertValue('[data-slot="details-alt"]', 'Het eerste team voor de tafels')
        ->assertSeeIn('[data-slot="details-alt-hint"]', 'Geldt overal waar dit bestand staat.')
        ->assertSeeIn('[data-slot="details-usage-heading"]', 'Gebruikt op 1 pagina')
        ->assertSeeIn('[data-slot="details-page-title"]', 'Onze teams')
        ->assertAttribute('[data-slot="details-page-link"]', 'href', '/made-cms/pages/'.$this->page->id.'/edit')
        ->assertAttribute('[data-slot="details-page-link"]', 'aria-label', 'Onze teams bewerken')
        ->assertSeeIn('[data-slot="details-page-places"]', 'Hero › Foto')
        ->assertVisible('[data-slot="details-crop"]')
        ->assertAttribute('[data-media-details]', 'aria-label', 'Gekozen bestand');

    expect($page->script("() => new URL(document.querySelector('[data-slot=\"details-thumb\"]').src).pathname"))->toBe('/storage/'.$this->teamfoto->thumb_path);

    $page->click(libraryTile($this->schema));

    expect($page->script(waitUntil(libraryDetails('wedstrijdschema.pdf'))))->toBeTrue();

    $page->assertSeeIn('[data-slot="details-alt-hint"]', 'Voor een document is dit de omschrijving bij de downloadlink.')
        ->assertAttribute('[data-slot="details-alt"]', 'placeholder', 'Bijvoorbeeld: Inschrijfformulier (pdf)')
        ->assertSeeIn('[data-slot="details-usage-heading"]', 'Gebruik')
        ->assertVisible('[data-slot="details-nowhere"]')
        ->assertMissing('[data-slot="details-crop"]')
        ->assertNoJavaScriptErrors();
});

it('marks a page in the bin and a place only in the draft', function (): void {
    $draft = Page::factory()->published()->withStrips([])->withDraft([storedStrip('hero', ['foto' => $this->teamfoto->id], id: 'h')])->create(['title' => 'Home']);
    $bin = Page::factory()->withStrips([storedStrip('hero', ['foto' => $this->teamfoto->id], id: 'h')])->create(['title' => 'Proefles']);
    SyncMediaUsage::run($draft);
    SyncMediaUsage::run($bin);
    $bin->delete();

    $page = visit('/made-cms/media?file='.$this->teamfoto->id);

    expect($page->script(waitUntil(libraryDetails('teamfoto-merwestad-1.webp'))))->toBeTrue()
        ->and($page->script("() => [...document.querySelectorAll('.bibliotheek-pagina')].map((page) => [page.querySelector('[data-slot=\"details-page-title\"], [data-slot=\"details-page-name\"]').textContent, page.querySelector('[data-slot=\"details-page-link\"]') !== null, page.querySelector('[data-slot=\"details-page-note\"]').hidden ? null : page.querySelector('[data-slot=\"details-page-note\"]').textContent])"))
        ->toBe([
            ['Home', true, 'Alleen in het concept'],
            ['Onze teams', true, null],
            ['Proefles', false, 'In de prullenbak, telt niet mee'],
        ]);

    $page->assertSeeIn('[data-slot="details-usage-heading"]', 'Gebruikt op 2 pagina’s')
        ->assertAttribute(libraryTile($this->teamfoto), 'aria-pressed', 'true')
        ->assertNoJavaScriptErrors();
});

it('saves the alt text of a file, and the amber dot leaves its tile', function (): void {
    $page = visit('/made-cms/media');

    $page->click(libraryTile($this->training));

    expect($page->script(waitUntil(libraryDetails('training-dinsdag.webp'))))->toBeTrue();

    $page->assertSeeIn('[data-slot="details-alt-hint"]', 'Nog leeg. Zonder alt-tekst mist Google en een schermlezer wat erop staat.')
        ->assertVisible('[data-slot="details-no-alt"]')
        ->assertAttribute('[data-slot="details-save"]', 'disabled', '')
        ->type('[data-slot="details-alt"]', 'Spelers aan vier tafels tijdens de dinsdagtraining')
        ->assertSeeIn('[data-slot="details-changed"]', 'Enter slaat ook op.')
        ->click('[data-slot="details-save"]');

    expect($page->script(waitUntil("document.querySelector('[data-slot=\"details-status\"]').textContent === 'Opgeslagen'")))->toBeTrue()
        ->and($this->training->refresh()->alt)->toBe('Spelers aan vier tafels tijdens de dinsdagtraining')
        ->and($page->script(waitUntil("document.querySelector('[data-slot=\"media-without-alt-count\"]').textContent === '1'")))->toBeTrue()
        ->and($page->script("() => document.querySelector('".libraryTile($this->training)." [data-slot=\"media-tile-no-alt\"]').hidden"))->toBeTrue();

    $page->assertMissing('[data-slot="details-no-alt"]')
        ->assertSeeIn('[data-slot="details-alt-hint"]', 'Geldt overal waar dit bestand staat.')
        // Enter saves too, and choosing another file saves what the editor typed first.
        ->type('[data-slot="details-alt"]', 'Spelers aan vier tafels')
        ->keys('[data-slot="details-alt"]', 'Enter');

    expect($page->script(waitUntil("document.querySelector('[data-slot=\"details-status\"]').textContent === 'Opgeslagen'")))->toBeTrue()
        ->and($this->training->refresh()->alt)->toBe('Spelers aan vier tafels');

    $page->type('[data-slot="details-alt"]', 'Spelers tijdens de training')
        ->click(libraryTile($this->clubavond));

    expect($page->script(waitUntil(libraryDetails('clubavond.webp'))))->toBeTrue()
        ->and($page->script(waitUntil("! document.querySelector('[data-slot=\"details-alt\"]').disabled")))->toBeTrue()
        ->and($this->training->refresh()->alt)->toBe('Spelers tijdens de training');

    $page->assertNoJavaScriptErrors();
});

it('refuses an alt text that is too long and keeps the choice until it is shorter', function (): void {
    $page = visit('/made-cms/media');

    $page->click(libraryTile($this->training));

    expect($page->script(waitUntil(libraryDetails('training-dinsdag.webp'))))->toBeTrue();

    $page->type('[data-slot="details-alt"]', str_repeat('Lang ', 60))
        ->click(libraryTile($this->clubavond))
        ->assertSeeIn('[data-slot="details-name"]', 'training-dinsdag.webp')
        ->assertAttribute(libraryTile($this->clubavond), 'aria-pressed', 'false')
        ->assertSeeIn('[data-slot="details-alt-hint"]', 'Een alt-tekst mag hoogstens 255 tekens zijn.')
        ->assertAttribute('[data-slot="details-alt-hint"]', 'role', 'alert');

    expect($page->script('() => document.activeElement?.dataset.slot'))->toBe('details-alt')
        ->and($this->training->refresh()->alt)->toBeNull();

    $page->type('[data-slot="details-alt"]', 'Spelers aan vier tafels')
        ->click('[data-slot="details-save"]');

    expect($page->script(waitUntil("document.querySelector('[data-slot=\"details-status\"]').textContent === 'Opgeslagen'")))->toBeTrue()
        ->and($this->training->refresh()->alt)->toBe('Spelers aan vier tafels');

    $page->assertNoJavaScriptErrors();
});

it('keeps the alt text the editor typed when the chosen tile is clicked again', function (): void {
    $page = visit('/made-cms/media');

    $page->click(libraryTile($this->teamfoto));

    expect($page->script(waitUntil(libraryDetails('teamfoto-merwestad-1.webp'))))->toBeTrue();

    // A click on the file that is chosen chooses nothing: the typed text stays, unsaved.
    $page->type('[data-slot="details-alt"]', 'Het team aan de tafels')
        ->click(libraryTile($this->teamfoto))
        ->assertValue('[data-slot="details-alt"]', 'Het team aan de tafels')
        ->assertVisible('[data-slot="details-changed"]');

    expect($page->script(waitUntil(libraryDetails('teamfoto-merwestad-1.webp'))))->toBeTrue();

    $page->keys('[data-slot="details-alt"]', 'Enter');

    expect($page->script(waitUntil("document.querySelector('[data-slot=\"details-status\"]').textContent === 'Opgeslagen'")))->toBeTrue();

    $page->click(libraryTile($this->teamfoto))
        ->assertValue('[data-slot="details-alt"]', 'Het team aan de tafels')
        ->assertAttribute('[data-slot="details-save"]', 'disabled', '')
        ->click(libraryTile($this->clubavond));

    expect($page->script(waitUntil(libraryDetails('clubavond.webp'))))->toBeTrue()
        ->and($this->teamfoto->refresh()->alt)->toBe('Het team aan de tafels');

    $page->click(libraryTile($this->teamfoto))
        ->assertValue('[data-slot="details-alt"]', 'Het team aan de tafels')
        ->assertNoJavaScriptErrors();
});

it('stores a typed alt text before the choice moves to another file, which waits for it', function (): void {
    $page = visit('/made-cms/media');

    // The first alt text waits for the test before it travels.
    $page->script(<<<'JS'
        () => {
            const original = window.fetch;

            window.releaseAlt = null;
            window.fetch = (input, init = {}) => {
                if (! String(input).endsWith('/alt') || window.releaseAlt !== null) {
                    return original(input, init);
                }

                return new Promise((resolve, reject) => {
                    window.releaseAlt = () => original(input, init).then(resolve, reject);
                });
            };
        }
        JS);
    $page->click(libraryTile($this->teamfoto));

    expect($page->script(waitUntil(libraryDetails('teamfoto-merwestad-1.webp'))))->toBeTrue();

    $page->type('[data-slot="details-alt"]', 'Het team aan de tafels')
        ->click(libraryTile($this->clubavond));

    expect($page->script(waitUntil('window.releaseAlt !== null')))->toBeTrue();

    // While the text travels the photo stays chosen, busy with the text the editor typed.
    $page->assertSeeIn('[data-slot="details-name"]', 'teamfoto-merwestad-1.webp')
        ->assertValue('[data-slot="details-alt"]', 'Het team aan de tafels')
        ->assertAttribute('[data-slot="details-alt"]', 'disabled', '')
        ->assertSeeIn('[data-slot="details-save"]', 'Bezig…')
        ->assertAttribute(libraryTile($this->teamfoto), 'aria-pressed', 'true')
        ->assertAttribute(libraryTile($this->clubavond), 'aria-pressed', 'false');

    $page->script('() => window.releaseAlt()');

    expect($page->script(waitUntil(libraryDetails('clubavond.webp'))))->toBeTrue()
        ->and($this->teamfoto->refresh()->alt)->toBe('Het team aan de tafels');

    $page->assertAttribute(libraryTile($this->clubavond), 'aria-pressed', 'true')
        ->click(libraryTile($this->teamfoto));

    expect($page->script(waitUntil(libraryDetails('teamfoto-merwestad-1.webp'))))->toBeTrue();

    $page->assertValue('[data-slot="details-alt"]', 'Het team aan de tafels')
        ->assertNoJavaScriptErrors();
});

it('keeps the choice and the typed alt text when storing it fails, and says why', function (): void {
    $page = visit('/made-cms/media');

    // The first alt text meets a network that is gone, the second an expired session; the third goes through.
    $page->script(<<<'JS'
        () => {
            const original = window.fetch;
            const answers = ['offline', 419];

            window.fetch = (input, init = {}) => {
                if (! String(input).endsWith('/alt') || answers.length === 0) {
                    return original(input, init);
                }

                const answer = answers.shift();

                return answer === 'offline'
                    ? Promise.reject(new TypeError('Failed to fetch'))
                    : Promise.resolve(new Response('{}', { status: answer, headers: { 'Content-Type': 'application/json' } }));
            };
        }
        JS);
    $page->click(libraryTile($this->teamfoto));

    expect($page->script(waitUntil(libraryDetails('teamfoto-merwestad-1.webp'))))->toBeTrue();

    $page->type('[data-slot="details-alt"]', 'Het team aan de tafels')
        ->click(libraryTile($this->clubavond));

    expect($page->script(waitUntil("document.querySelector('[data-slot=\"details-alt-hint\"]').textContent === 'Opslaan lukte niet. Controleer je verbinding en probeer het opnieuw.'")))->toBeTrue();

    $page->assertSeeIn('[data-slot="details-name"]', 'teamfoto-merwestad-1.webp')
        ->assertValue('[data-slot="details-alt"]', 'Het team aan de tafels')
        ->assertAttribute('[data-slot="details-alt-hint"]', 'role', 'alert')
        ->assertAttribute(libraryTile($this->teamfoto), 'aria-pressed', 'true')
        ->assertAttribute(libraryTile($this->clubavond), 'aria-pressed', 'false');

    expect($page->script('() => document.activeElement?.dataset.slot'))->toBe('details-alt')
        ->and($this->teamfoto->refresh()->alt)->toBe('Het eerste team voor de tafels');

    $page->click(libraryTile($this->clubavond));

    expect($page->script(waitUntil("document.querySelector('[data-slot=\"details-alt-hint\"]').textContent === 'Je sessie is verlopen. Herlaad de pagina om verder te gaan.'")))->toBeTrue();

    $page->assertSeeIn('[data-slot="details-name"]', 'teamfoto-merwestad-1.webp')
        ->assertValue('[data-slot="details-alt"]', 'Het team aan de tafels')
        ->assertAttribute(libraryTile($this->clubavond), 'aria-pressed', 'false');

    expect($this->teamfoto->refresh()->alt)->toBe('Het eerste team voor de tafels');

    $page->click(libraryTile($this->clubavond));

    expect($page->script(waitUntil(libraryDetails('clubavond.webp'))))->toBeTrue()
        ->and($this->teamfoto->refresh()->alt)->toBe('Het team aan de tafels');

    $page->assertNoJavaScriptErrors();
});

it('uploads from "Uploaden" into the folder it is in, and the amber dot leaves once the new photo gets its alt text', function (): void {
    $page = visit('/made-cms/media?folder='.$this->teams->id);

    $page->script(<<<'JS'
        () => document.querySelector('[data-slot="upload-input"]').addEventListener('click', (event) => {
            event.preventDefault();
            window.chooserOpened = true;
        })
        JS);
    $page->click('[data-slot="library-upload"]');

    expect($page->script('() => window.chooserOpened === true'))->toBeTrue();

    $page->attach('[data-slot="upload-input"]', quadrants(2400, 1600, 'jpg', 'Teamfoto Merwestad 5.jpg'));

    expect($page->script(waitUntil("document.querySelector('[data-media-crop]').open && ! document.querySelector('[data-slot=\"crop-save\"]').disabled")))->toBeTrue();

    // The editor crops it without an alt text, chooses it in the list, and gives it one there: the amber dot leaves.
    $page->assertVisible('[data-slot="media-upload"]')
        ->assertSeeIn('[data-slot="crop-eyebrow"]', 'Uploaden')
        ->click('[data-slot="crop-save"]');

    expect($page->script(waitUntil("! document.querySelector('[data-media-crop]').open && document.querySelector('[data-slot=\"media-upload\"]').hidden")))->toBeTrue()
        ->and($page->script(waitUntil(libraryShows(['teamfoto-merwestad-5', 'wedstrijdschema', 'training-dinsdag', 'teamfoto-merwestad-1']))))->toBeTrue()
        ->and($page->script(waitUntil("document.querySelector('[data-slot=\"media-kinds\"] .filter[data-kind=\"image\"] [data-slot=\"media-kind-count\"]').textContent === '4'")))->toBeTrue()
        ->and($page->script("() => document.querySelector('[data-slot=\"media-without-alt-count\"]').textContent"))->toBe('3');

    $photo = Media::query()->where('name', 'teamfoto-merwestad-5')->sole();

    expect($photo->folder_id)->toBe($this->teams->id);

    $page->assertVisible(libraryTile($photo).' [data-slot="media-tile-no-alt"]')
        ->click(libraryTile($photo));

    expect($page->script(waitUntil(libraryDetails('teamfoto-merwestad-5.webp'))))->toBeTrue();

    $page->type('[data-slot="details-alt"]', 'Team Merwestad 5 voor de clubvlag')
        ->click('[data-slot="details-save"]');

    expect($page->script(waitUntil("document.querySelector('".libraryTile($photo)." [data-slot=\"media-tile-no-alt\"]').hidden && document.querySelector('[data-slot=\"media-without-alt-count\"]').textContent === '2'")))->toBeTrue()
        ->and($photo->refresh()->alt)->toBe('Team Merwestad 5 voor de clubvlag');

    $page->assertNoJavaScriptErrors();
});

it('takes files dropped on the files, says where they go, and asks the alt text of a document in the field', function (): void {
    $page = visit('/made-cms/media?folder='.$this->teams->id);
    $files = [
        ['name' => 'Clubavond oktober.jpg', 'type' => 'image/jpeg', 'path' => quadrants(1200, 800, 'jpg', 'Clubavond oktober.jpg')],
        ['name' => 'Inschrijfformulier.pdf', 'type' => 'application/pdf', 'path' => mediaFixture('wedstrijdschema.pdf')],
    ];

    expect($page->script(dropOnLibrary($files, drop: false)))->toBe(2);

    $page->assertVisible('[data-slot="library-drop"]')
        ->assertSeeIn('[data-slot="library-drop-title"]', 'Laat los om te uploaden naar Teams')
        ->assertSeeIn('[data-slot="library-drop-line"]', '2 bestanden · Foto’s, documenten en video’s, tot 25 MB per bestand')
        ->screenshot(filename: 'media-library-drop');

    $page->script(dropOnLibrary($files, drop: true));

    expect($page->script(waitUntil("document.querySelector('[data-media-crop]').open && ! document.querySelector('[data-slot=\"crop-save\"]').disabled")))->toBeTrue();

    $page->assertMissing('[data-slot="library-drop"]')
        ->click('[data-slot="crop-save"]');

    // Back from the step, the field asks the description of the document.
    expect($page->script(waitUntil("! document.querySelector('[data-media-crop]').open && document.activeElement?.dataset.slot === 'upload-row-alt-input'")))->toBeTrue();

    $page->assertSeeIn('[data-upload] [data-slot="upload-row-alt-hint"]:not([hidden])', 'Voor een document is dit de omschrijving bij de downloadlink.')
        ->type('[data-upload] .upload-rij:nth-child(2) [data-slot="upload-row-alt-input"]', 'Inschrijfformulier voor nieuwe leden (pdf)')
        ->keys('[data-upload] .upload-rij:nth-child(2) [data-slot="upload-row-alt-input"]', 'Enter');

    expect($page->script(waitUntil("document.querySelector('[data-slot=\"media-upload\"]').hidden")))->toBeTrue()
        ->and(Media::query()->where('name', 'inschrijfformulier')->sole()->only('alt', 'folder_id'))->toBe(['alt' => 'Inschrijfformulier voor nieuwe leden (pdf)', 'folder_id' => $this->teams->id])
        ->and($page->script(waitUntil("document.querySelectorAll('[data-media] .tegel').length === 5")))->toBeTrue();

    $page->assertNoJavaScriptErrors();
});

it('keeps its filter and search when files come in, and shows a new file only when it falls within them', function (): void {
    $page = visit('/made-cms/media?without_alt=1&q=training');

    expect($page->script(waitUntil(libraryShows(['training-dinsdag']))))->toBeTrue();

    $page->script(dropOnLibrary([
        ['name' => 'Inschrijfformulier.pdf', 'type' => 'application/pdf', 'path' => mediaFixture('wedstrijdschema.pdf')],
    ], drop: true));

    // The document asks its alt text in the field; the numbers follow, the filter and the search stay.
    expect($page->script(waitUntil("document.querySelector('[data-upload] [data-slot=\"upload-row-alt-input\"]')?.checkVisibility()")))->toBeTrue()
        ->and($page->script(waitUntil("document.querySelector('[data-slot=\"media-kinds\"] .filter[data-kind=\"\"]').textContent.replace(/\\s+/g, ' ').trim() === 'Alle 5'")))->toBeTrue();

    $page->assertValue('[data-slot="media-search"]', 'training')
        ->assertAttribute('[data-slot="media-without-alt"]', 'aria-pressed', 'true');

    expect($page->script(libraryShows(['training-dinsdag'])))->toBeTrue()
        ->and($page->script('() => location.search'))->toBe('?without_alt=1&q=training');

    $page->type('[data-upload] [data-slot="upload-row-alt-input"]', 'Inschrijfformulier voor nieuwe leden (pdf)')
        ->keys('[data-upload] [data-slot="upload-row-alt-input"]', 'Enter');

    expect($page->script(waitUntil("document.querySelector('[data-slot=\"media-upload\"]').hidden")))->toBeTrue()
        ->and(Media::query()->where('name', 'inschrijfformulier')->sole()->alt)->toBe('Inschrijfformulier voor nieuwe leden (pdf)')
        ->and($page->script(libraryShows(['training-dinsdag'])))->toBeTrue();

    $page->assertValue('[data-slot="media-search"]', 'training')
        ->assertNoJavaScriptErrors();
});

it('takes no files let go beside the library, so the browser never opens them and a typed alt text stays', function (): void {
    $page = visit('/made-cms/media');

    $page->click(libraryTile($this->training));

    expect($page->script(waitUntil(libraryDetails('training-dinsdag.webp'))))->toBeTrue();

    $page->type('[data-slot="details-alt"]', 'Spelers aan vier tafels');

    // Over the head with "Uploaden", the bar, the menu and the crop step the page takes the files and says it does not
    // take them (the browser would leave the CMS for the file otherwise); nothing uploads.
    $answers = $page->script(<<<'JS'
        () => ['.kop-met-actie', '[data-slot="library-upload"]', '.kopbalk', '#made-cms-menu', '[data-media-crop]'].map((target) => {
            const transfer = new DataTransfer();

            transfer.items.add(new File(['%PDF-1.4'], 'Inschrijfformulier.pdf', { type: 'application/pdf' }));

            const over = new DragEvent('dragover', { bubbles: true, cancelable: true, dataTransfer: transfer });
            const drop = new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer: transfer });

            document.querySelector(target).dispatchEvent(over);

            const effect = transfer.dropEffect;

            document.querySelector(target).dispatchEvent(drop);

            return [target, over.defaultPrevented, effect, drop.defaultPrevented];
        })
        JS);

    expect($answers)->toBe([
        ['.kop-met-actie', true, 'none', true],
        ['[data-slot="library-upload"]', true, 'none', true],
        ['.kopbalk', true, 'none', true],
        ['#made-cms-menu', true, 'none', true],
        ['[data-media-crop]', true, 'none', true],
    ]);

    $page->assertMissing('[data-slot="library-drop"]')
        ->assertMissing('[data-slot="media-upload"]')
        ->assertValue('[data-slot="details-alt"]', 'Spelers aan vier tafels');

    expect(Media::query()->count())->toBe(4);

    $page->assertNoJavaScriptErrors();
});

it('hands the photos of a batch to the crop step one after the other, and closes once they are back', function (): void {
    $page = visit('/made-cms/media');

    expect($page->script(dropOnLibrary([
        ['name' => 'Teamfoto.jpg', 'type' => 'image/jpeg', 'path' => quadrants(1200, 800, 'jpg', 'Teamfoto.jpg')],
        ['name' => 'Veld.jpg', 'type' => 'image/jpeg', 'path' => quadrants(800, 600, 'jpg', 'Veld.jpg')],
    ], drop: true)))->toBe(2);

    expect($page->script(waitUntil("document.querySelector('[data-media-crop]').open && ! document.querySelector('[data-slot=\"crop-save\"]').disabled")))->toBeTrue()
        // The field asks no alt text of the photos the step has.
        ->and($page->script("() => [...document.querySelectorAll('[data-slot=\"upload-row-alt-input\"]')].filter((input) => input.checkVisibility()).length"))->toBe(0);

    $page->assertSeeIn('[data-slot="crop-eyebrow"]', 'Uploaden / bestand 1 van 2')
        ->assertSeeIn('[data-slot="crop-step"]', 'Bestand 1 van 2 · daarna veld')
        ->assertSeeIn('[data-slot="crop-save"]', 'Opslaan en volgende')
        ->assertValue('[data-slot="crop-name"]', 'teamfoto')
        ->type('[data-slot="crop-alt"]', 'Het eerste team')
        ->click('[data-ratio="1:1"]')
        ->click('[data-slot="crop-save"]');

    expect($page->script(waitUntil("document.querySelector('[data-slot=\"crop-step\"]').textContent === 'Bestand 2 van 2' && ! document.querySelector('[data-slot=\"crop-save\"]').disabled")))->toBeTrue();

    $page->assertSeeIn('[data-slot="crop-save"]', 'Opslaan')
        ->type('[data-slot="crop-alt"]', 'Leden aan de bar')
        ->click('[data-slot="crop-save"]');

    expect($page->script(waitUntil("! document.querySelector('[data-media-crop]').open && document.querySelector('[data-slot=\"media-upload\"]').hidden")))->toBeTrue()
        ->and(Media::query()->where('name', 'teamfoto')->sole()->only('alt', 'width', 'height'))->toBe(['alt' => 'Het eerste team', 'width' => 800, 'height' => 800])
        ->and(Media::query()->where('name', 'veld')->sole()->alt)->toBe('Leden aan de bar');

    $page->assertNoJavaScriptErrors();
});

it('asks the alt text of a photo the editor did not get to in the crop step', function (): void {
    $page = visit('/made-cms/media');

    expect($page->script(dropOnLibrary([
        ['name' => 'Teamfoto.jpg', 'type' => 'image/jpeg', 'path' => quadrants(1200, 800, 'jpg', 'Teamfoto.jpg')],
        ['name' => 'Veld.jpg', 'type' => 'image/jpeg', 'path' => quadrants(800, 600, 'jpg', 'Veld.jpg')],
    ], drop: true)))->toBe(2);

    expect($page->script(waitUntil("document.querySelector('[data-media-crop]').open && ! document.querySelector('[data-slot=\"crop-save\"]').disabled")))->toBeTrue();

    $page->type('[data-slot="crop-alt"]', 'Het eerste team')
        ->keys('[data-slot="crop-alt"]', 'Escape');

    expect($page->script(waitUntil("! document.querySelector('[data-media-crop]').open && document.activeElement?.dataset.slot === 'upload-row-alt-input'")))->toBeTrue()
        ->and(Media::query()->where('name', 'teamfoto')->sole()->alt)->toBe('Het eerste team')
        ->and($page->script("() => [...document.querySelectorAll('[data-slot=\"upload-row-alt-input\"]')].filter((input) => input.checkVisibility()).length"))->toBe(1);

    $page->type('[data-upload] li:nth-child(2) [data-slot="upload-row-alt-input"]', 'Het veld achter de sporthal')
        ->click('[data-slot="upload-save"]');

    expect($page->script(waitUntil("document.querySelector('[data-slot=\"media-upload\"]').hidden")))->toBeTrue()
        ->and(Media::query()->where('name', 'veld')->sole()->alt)->toBe('Het veld achter de sporthal');

    $page->assertNoJavaScriptErrors();
});

it('crops the chosen photo from the side column, and its tile shows the new crop', function (): void {
    $photo = UploadMedia::run(new UploadedMedia(quadrants(2400, 1600), 'Teamfoto Merwestad 6.jpg', $this->teams->id));
    $page = visit('/made-cms/media');

    $page->click(libraryTile($photo));

    expect($page->script(waitUntil(libraryDetails('teamfoto-merwestad-6.webp'))))->toBeTrue();

    $page->assertSeeIn(libraryTile($photo).' [data-slot="media-tile-meta"]', '1920 × 1280')
        ->click('[data-slot="details-crop"]');

    expect($page->script(waitUntil("document.querySelector('[data-media-crop]').open && ! document.querySelector('[data-slot=\"crop-save\"]').disabled")))->toBeTrue();

    $page->assertSeeIn('[data-slot="crop-eyebrow"]', 'Media / bijsnijden')
        ->click('[data-ratio="16:9"]')
        ->click('[data-slot="crop-save"]');

    expect($page->script(waitUntil("! document.querySelector('[data-media-crop]').open && document.querySelector('".libraryTile($photo)." [data-slot=\"media-tile-meta\"]').textContent.startsWith('1920 × 1080')")))->toBeTrue()
        ->and($page->script(waitUntil("document.querySelector('[data-slot=\"details-line\"]').textContent.includes('1920 × 1080')")))->toBeTrue()
        ->and($page->script('() => document.activeElement?.dataset.slot'))->toBe('details-crop')
        ->and($photo->refresh()->height)->toBe(1080);

    $page->assertNoJavaScriptErrors();
});

it('opens with the file of its address in the side column, also when its tile is not on the first page', function (): void {
    Media::factory()->count(40)->in($this->nieuws)->create(['created_at' => now()->addMinute()]);
    $page = visit('/made-cms/media?file='.$this->teamfoto->id);

    expect($page->script(waitUntil(libraryDetails('teamfoto-merwestad-1.webp'))))->toBeTrue();

    $page->assertMissing(libraryTile($this->teamfoto))
        ->assertSeeIn('[data-slot="details-usage-heading"]', 'Gebruikt op 1 pagina')
        ->assertNoJavaScriptErrors();
});

it('shows the start of an empty library, and the list once files are on their way', function (): void {
    MediaUsage::query()->delete();
    Media::query()->delete();
    $page = visit('/made-cms/media');

    $page->assertVisible('[data-slot="media-start"]')
        ->assertSeeIn('[data-slot="media-start"]', 'Nog geen bestanden')
        ->assertSeeIn('[data-slot="media-start"]', 'Of sleep bestanden van je computer hierheen. Foto’s, documenten en video’s, tot 25 MB per bestand.')
        ->assertMissing('[data-slot="media-search"]')
        ->assertMissing('[data-media-details]')
        ->screenshot(filename: 'media-library-empty');

    expect($page->script(dropOnLibrary([
        ['name' => 'Inschrijfformulier.pdf', 'type' => 'application/pdf', 'path' => mediaFixture('wedstrijdschema.pdf')],
    ], drop: true, target: '[data-media-library]')))->toBe(1);

    expect($page->script(waitUntil("document.querySelector('[data-slot=\"media-start\"]').hidden && ! document.querySelector('[data-slot=\"media-upload\"]').hidden")))->toBeTrue()
        ->and($page->script(waitUntil(libraryShows(['inschrijfformulier']))))->toBeTrue();

    $page->assertNoJavaScriptErrors();
});

it('puts the side column below the files on a phone, and brings it into view when a file is chosen', function (): void {
    $page = visit('/made-cms/media')->resize(390, 844);

    $page->click(libraryTile($this->teamfoto));

    expect($page->script(waitUntil(libraryDetails('teamfoto-merwestad-1.webp'))))->toBeTrue()
        // The page scrolls down to it, as far as it goes.
        ->and($page->script(waitUntil('window.scrollY > 300 && document.querySelector(\'[data-media-details]\').getBoundingClientRect().top < 300')))->toBeTrue();

    $page->assertVisible('[data-slot="details-back"]')
        ->screenshot(filename: 'media-library-phone')
        ->assertNoJavaScriptErrors();
});

it('follows the dark colours of the system', function (): void {
    $page = visit('/made-cms/media?file='.$this->schema->id)->inDarkMode()->resize(1280, 1100);

    expect($page->script(waitUntil(libraryDetails('wedstrijdschema.pdf'))))->toBeTrue()
        ->and($page->script(<<<'JS'
            () => {
                const colour = (selector, property) => getComputedStyle(document.querySelector(selector))[property];

                return [
                    colour('body', 'backgroundColor'),
                    colour('[data-media-details]', 'backgroundColor'),
                    colour('[data-media] .tegel[data-kind="image"] .tegel-beeld', 'backgroundColor'),
                    colour('[data-media] .tegel[data-kind="document"] .tegel-beeld', 'backgroundColor'),
                    colour('[data-slot="details-mini"]', 'backgroundColor'),
                ];
            }
            JS))->toBe(['rgb(14, 43, 38)', 'rgb(27, 61, 54)', 'rgb(27, 61, 54)', 'rgb(243, 241, 232)', 'rgb(243, 241, 232)']);

    $page->screenshot(filename: 'media-library-dark')
        ->assertNoJavaScriptErrors();
});

/**
 * A script for $page->script() that notes whether the layer for files from the computer shows at any moment.
 */
function watchUploadLayer(): string
{
    return "() => { window.madeCmsUploadLayer = false; new MutationObserver(() => { if (! document.querySelector('[data-slot=\"library-drop\"]').hidden) { window.madeCmsUploadLayer = true; } }).observe(document.querySelector('[data-slot=\"library-drop\"]'), { attributes: true }); return true; }";
}

/**
 * A script for $page->script() that clicks a tile with Shift, Cmd or Ctrl held.
 */
function clickTileWith(Media $media, string $key): string
{
    return "() => { document.querySelector('".libraryTile($media)."').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, {$key}: true })); return true; }";
}

function libraryNotice(): string
{
    return "document.querySelector('[data-slot=\"media-notice\"] [data-slot=\"media-notice-text\"]')?.textContent ?? ''";
}

it('creates a folder in the field below the chips, and refuses a name another folder has', function (): void {
    $page = visit('/made-cms/media')->resize(1280, 1100);

    $page->click('[data-library-new-folder]')
        ->assertVisible('[data-slot="media-folder-field"]')
        ->assertAttribute('[data-library-new-folder]', 'aria-expanded', 'true')
        ->assertSeeIn('[data-slot="media-folder-label"]', 'Naam van de nieuwe map')
        ->assertSeeIn('[data-slot="media-folder-hint"]', 'Hoogstens 60 tekens. Enter maakt de map aan, Escape annuleert.')
        ->assertSeeIn('[data-slot="media-folder-confirm"]', 'Aanmaken');

    expect($page->script('() => document.activeElement?.dataset.slot'))->toBe('media-folder-input');

    $page->type('[data-slot="media-folder-input"]', 'teams')
        ->keys('[data-slot="media-folder-input"]', 'Enter');

    expect($page->script(waitUntil("document.querySelector('[data-slot=\"media-folder-hint\"]').getAttribute('role') === 'alert'")))->toBeTrue();

    $page->assertSeeIn('[data-slot="media-folder-hint"]', 'Er is al een map Teams.')
        ->screenshot(filename: 'media-library-folder-taken')
        ->type('[data-slot="media-folder-input"]', '  Jeugd ')
        ->keys('[data-slot="media-folder-input"]', 'Enter');

    expect($page->script(waitUntil(libraryNotice()." === 'Map Jeugd aangemaakt'")))->toBeTrue()
        ->and($page->script(waitUntil("document.activeElement?.closest('.kmap')?.textContent.includes('Jeugd') === true")))->toBeTrue()
        ->and($page->script(libraryFolders()))->toBe([['Jeugd', '0', 'false'], ['Nieuws', '1', 'false'], ['Teams', '3', 'false']])
        // The editor stays where they were, with every file.
        ->and($page->script(libraryShows(['wedstrijdschema', 'clubavond', 'training-dinsdag', 'teamfoto-merwestad-1'])))->toBeTrue()
        ->and(MediaFolder::query()->where('name', 'Jeugd')->exists())->toBeTrue();

    $page->assertMissing('[data-slot="media-folder-field"]')
        ->assertAttribute('[data-library-new-folder]', 'aria-expanded', 'false')
        ->screenshot(filename: 'media-library-folder-created')
        // Escape closes the field without a folder.
        ->click('[data-library-new-folder]')
        ->type('[data-slot="media-folder-input"]', 'Locatie')
        ->keys('[data-slot="media-folder-input"]', 'Escape')
        ->assertMissing('[data-slot="media-folder-field"]')
        ->assertNoJavaScriptErrors();

    expect(MediaFolder::query()->where('name', 'Locatie')->exists())->toBeFalse();
});

it('moves a file dragged onto a new folder, without the layer for uploads, and the numbers follow', function (): void {
    $page = visit('/made-cms/media?folder='.$this->teams->id)->resize(1280, 1100);

    expect($page->script(waitUntil(libraryShows(['wedstrijdschema', 'training-dinsdag', 'teamfoto-merwestad-1']))))->toBeTrue();

    $page->click('[data-library-new-folder]')
        ->type('[data-slot="media-folder-input"]', 'Jeugd')
        ->click('[data-slot="media-folder-confirm"]');

    expect($page->script(waitUntil(libraryNotice()." === 'Map Jeugd aangemaakt'")))->toBeTrue()
        ->and($page->script(watchUploadLayer()))->toBeTrue();

    $jeugd = MediaFolder::query()->where('name', 'Jeugd')->sole();

    $page->drag(libraryTile($this->training), '[data-media] .kmap[data-folder="'.$jeugd->id.'"]');

    expect($page->script(waitUntil(libraryShows(['wedstrijdschema', 'teamfoto-merwestad-1']))))->toBeTrue()
        ->and($page->script(waitUntil(libraryNotice()." === 'training-dinsdag.webp staat nu in Jeugd'")))->toBeTrue()
        ->and($page->script(waitUntil("document.querySelector('[data-media] .kmap[data-folder=\"{$jeugd->id}\"] .kmap-aantal').textContent === '1'")))->toBeTrue()
        ->and($page->script(libraryFolders()))->toBe([['Jeugd', '1', 'false'], ['Nieuws', '1', 'false'], ['Teams', '2', 'true']])
        ->and($page->script('() => window.madeCmsUploadLayer'))->toBeFalse()
        ->and($page->script("() => document.querySelectorAll('.doel, .doel-actief, .geen-doel, .sleept').length"))->toBe(0)
        ->and($this->training->refresh()->folder_id)->toBe($jeugd->id);

    // The file left the folder the list shows: the message opens the folder it went to.
    $page->assertSeeIn('[data-slot="media-notice"] [data-slot="media-notice-link"]', 'Open Jeugd')
        ->screenshot(filename: 'media-library-dragged')
        ->click('[data-slot="media-notice"] [data-slot="media-notice-link"]');

    expect($page->script(waitUntil(libraryShows(['training-dinsdag']))))->toBeTrue()
        ->and($page->script(libraryNotice()))->toBe('');

    $page->assertSeeIn('[data-slot="media-trail-here"]', 'Jeugd')
        ->assertNoJavaScriptErrors();
});

it('stores a typed alt text before the chosen file is dragged onto a folder, and holds the file when that fails', function (): void {
    // In all files the file stays chosen and shows the text as stored.
    $page = visit('/made-cms/media?file='.$this->teamfoto->id)->resize(1280, 1100);

    expect($page->script(waitUntil(libraryDetails('teamfoto-merwestad-1.webp'))))->toBeTrue();

    $page->type('[data-slot="details-alt"]', 'Het team aan de tafels')
        ->drag(libraryTile($this->teamfoto), '[data-media] .kmap[data-folder="'.$this->nieuws->id.'"]');

    expect($page->script(waitUntil(libraryNotice()." === 'Staat nu in Nieuws'")))->toBeTrue()
        ->and($page->script(waitUntil("document.querySelector('[data-slot=\"details-line\"]').textContent.includes('map Nieuws')")))->toBeTrue()
        ->and($this->teamfoto->refresh()->alt)->toBe('Het team aan de tafels')
        ->and($this->teamfoto->folder_id)->toBe($this->nieuws->id);

    $page->assertValue('[data-slot="details-alt"]', 'Het team aan de tafels')
        ->assertNoJavaScriptErrors();

    // From the open folder the file leaves the side column, with its text stored first.
    $page = visit('/made-cms/media?folder='.$this->teams->id.'&file='.$this->training->id)->resize(1280, 1100);

    expect($page->script(waitUntil(libraryDetails('training-dinsdag.webp'))))->toBeTrue();

    $page->type('[data-slot="details-alt"]', 'De training op dinsdag')
        ->drag(libraryTile($this->training), '[data-media] .kmap[data-folder="'.$this->nieuws->id.'"]');

    expect($page->script(waitUntil(libraryShows(['wedstrijdschema']))))->toBeTrue()
        ->and($page->script(waitUntil(libraryNotice()." === 'training-dinsdag.webp staat nu in Nieuws'")))->toBeTrue()
        ->and($this->training->refresh()->alt)->toBe('De training op dinsdag')
        ->and($this->training->folder_id)->toBe($this->nieuws->id);

    $page->assertVisible('[data-slot="details-nothing"]')
        ->assertNoJavaScriptErrors();

    // A text that is not stored holds the file where it is, with the message at the text and nothing moved.
    $page = visit('/made-cms/media?folder='.$this->teams->id.'&file='.$this->schema->id)->resize(1280, 1100);

    expect($page->script(waitUntil(libraryDetails('wedstrijdschema.pdf'))))->toBeTrue();

    $page->script(<<<'JS'
        () => {
            const original = window.fetch;

            window.moves = 0;
            window.fetch = (input, init = {}) => {
                if (String(input).endsWith('/media/move')) {
                    window.moves++;
                }

                return String(input).endsWith('/alt') ? Promise.reject(new TypeError('Failed to fetch')) : original(input, init);
            };
        }
        JS);
    $page->type('[data-slot="details-alt"]', 'Het schema van het najaar')
        ->drag(libraryTile($this->schema), '[data-media] .kmap[data-folder="'.$this->nieuws->id.'"]');

    expect($page->script(waitUntil("document.querySelector('[data-slot=\"details-alt-hint\"]').textContent === 'Opslaan lukte niet. Controleer je verbinding en probeer het opnieuw.'")))->toBeTrue()
        ->and($page->script('() => window.moves'))->toBe(0)
        ->and($page->script(libraryNotice()))->toBe('')
        ->and($page->script('() => document.activeElement?.dataset.slot'))->toBe('details-alt')
        ->and($page->script(libraryShows(['wedstrijdschema'])))->toBeTrue()
        ->and($this->schema->refresh()->folder_id)->toBe($this->teams->id);

    $page->assertValue('[data-slot="details-alt"]', 'Het schema van het najaar')
        ->assertSeeIn('[data-slot="details-name"]', 'wedstrijdschema.pdf')
        ->assertNoJavaScriptErrors();
});

it('selects files with the keyboard and moves them with the folder of the side column', function (): void {
    $page = visit('/made-cms/media')->resize(1280, 1100);

    $page->click('[data-slot="media-select"]')
        ->assertAttribute('[data-slot="media-select"]', 'aria-pressed', 'true')
        ->assertSeeIn('[data-slot="details-selection-count"]', 'Nog niets gekozen')
        ->assertSeeIn('[data-slot="details-selection-hint"]', 'Kies de bestanden die je wilt verplaatsen.')
        ->assertMissing('[data-slot="details-move"]')
        ->assertMissing('[data-slot="details-nothing"]')
        ->keys(libraryTile($this->training), 'Space')
        ->keys(libraryTile($this->clubavond), 'Enter')
        ->assertAttribute(libraryTile($this->training), 'aria-pressed', 'true')
        ->assertAttribute(libraryTile($this->clubavond), 'aria-pressed', 'true')
        ->assertAttribute(libraryTile($this->teamfoto), 'aria-pressed', 'false')
        ->assertSeeIn('[data-slot="details-selection-count"]', '2 bestanden gekozen')
        ->assertSeeIn('[data-slot="details-selection-names"]', 'training-dinsdag.webp, clubavond.webp')
        ->assertMissing('[data-slot="details-selection-hint"]');

    // Their folders differ, so "Map" asks for one first.
    expect($page->script("() => [...document.querySelectorAll('[data-slot=\"details-move-folder\"] option')].map((option) => option.textContent)"))
        ->toBe(['Kies een map', 'Alle bestanden', 'Nieuws', 'Teams'])
        ->and($page->script("() => document.querySelector('[data-slot=\"details-move-button\"]').disabled"))->toBeTrue();

    $page->screenshot(filename: 'media-library-selection')
        ->select('[data-slot="details-move-folder"]', (string) $this->nieuws->id)
        ->keys('[data-slot="details-move-button"]', 'Enter');

    expect($page->script(waitUntil(libraryNotice()." === '2 bestanden staan nu in Nieuws'")))->toBeTrue()
        ->and($page->script(waitUntil("document.querySelector('[data-slot=\"details-selection-count\"]').textContent === 'Nog niets gekozen'")))->toBeTrue()
        ->and($page->script(waitUntil("document.querySelector('[data-media] .kmap[data-folder=\"{$this->nieuws->id}\"] .kmap-aantal').textContent === '2'")))->toBeTrue()
        ->and($page->script(libraryFolders()))->toBe([['Nieuws', '2', 'false'], ['Teams', '2', 'false']])
        ->and($this->training->refresh()->folder_id)->toBe($this->nieuws->id)
        ->and($this->clubavond->refresh()->folder_id)->toBe($this->nieuws->id);

    // The selection is empty, the mode stays on; Escape stops it.
    $page->assertAttribute('[data-slot="media-select"]', 'aria-pressed', 'true')
        ->assertMissing('[data-slot="media-notice"] [data-slot="media-notice-link"]')
        ->keys('[data-slot="media-select"]', 'Escape')
        ->assertAttribute('[data-slot="media-select"]', 'aria-pressed', 'false')
        ->assertVisible('[data-slot="details-nothing"]')
        ->assertNoJavaScriptErrors();
});

it('moves the chosen file with the folder of the side column, without a mouse', function (): void {
    $page = visit('/made-cms/media?file='.$this->teamfoto->id)->resize(1280, 1100);

    expect($page->script(waitUntil(libraryDetails('teamfoto-merwestad-1.webp'))))->toBeTrue()
        ->and($page->script("() => document.querySelector('[data-slot=\"details-move-folder\"]').value"))->toBe((string) $this->teams->id)
        ->and($page->script("() => document.querySelector('[data-slot=\"details-move-button\"]').disabled"))->toBeTrue();

    $page->select('[data-slot="details-move-folder"]', (string) $this->nieuws->id)
        ->keys('[data-slot="details-move-button"]', 'Enter');

    // The file stays in the list of all files and in the side column, now in Nieuws.
    expect($page->script(waitUntil(libraryNotice()." === 'Staat nu in Nieuws'")))->toBeTrue()
        ->and($page->script(waitUntil("document.querySelector('[data-slot=\"details-line\"]').textContent.includes('map Nieuws')")))->toBeTrue()
        ->and($page->script("() => document.querySelector('[data-slot=\"details-move-folder\"]').value"))->toBe((string) $this->nieuws->id)
        ->and($this->teamfoto->refresh()->folder_id)->toBe($this->nieuws->id);

    // A folder that went in the meantime: the refusal stays below "Map", and the folder leaves the list.
    $this->nieuws->media()->update(['folder_id' => null]);
    $gone = MediaFolder::factory()->create(['name' => 'Locatie']);
    $page = visit('/made-cms/media?file='.$this->teamfoto->id);

    expect($page->script(waitUntil(libraryDetails('teamfoto-merwestad-1.webp'))))->toBeTrue();

    $page->select('[data-slot="details-move-folder"]', (string) $gone->id);
    $gone->delete();
    $page->click('[data-slot="details-move-button"]');

    expect($page->script(waitUntil("! document.querySelector('[data-slot=\"details-move-error\"]').hidden")))->toBeTrue();

    $page->assertSeeIn('[data-slot="details-move-error"]', 'Deze map bestaat niet meer. Kies een andere map.')
        ->assertAttribute('[data-slot="details-move-error"]', 'role', 'alert');

    expect($page->script(waitUntil("! [...document.querySelectorAll('[data-slot=\"details-move-folder\"] option')].some((option) => option.textContent === 'Locatie')")))->toBeTrue();

    $page->assertSeeIn('[data-slot="details-move-error"]', 'Deze map bestaat niet meer.')
        ->assertNoJavaScriptErrors();
});

it('selects a range with Shift and drags the whole selection onto a folder', function (): void {
    $page = visit('/made-cms/media?file='.$this->schema->id)->resize(1280, 1100);

    expect($page->script(waitUntil(libraryDetails('wedstrijdschema.pdf'))))->toBeTrue()
        ->and($page->script(clickTileWith($this->training, 'shiftKey')))->toBeTrue()
        ->and($page->script(waitUntil("document.querySelector('[data-slot=\"media-select\"]').getAttribute('aria-pressed') === 'true'")))->toBeTrue();

    $page->assertSeeIn('[data-slot="details-selection-count"]', '3 bestanden gekozen')
        ->assertAttribute(libraryTile($this->teamfoto), 'aria-pressed', 'false');

    // Cmd or Ctrl takes one out again.
    expect($page->script(clickTileWith($this->clubavond, 'ctrlKey')))->toBeTrue();

    $page->assertSeeIn('[data-slot="details-selection-count"]', '2 bestanden gekozen')
        ->drag(libraryTile($this->training), '[data-media] .kmap[data-folder="'.$this->nieuws->id.'"]');

    expect($page->script(waitUntil(libraryNotice()." === '2 bestanden staan nu in Nieuws'")))->toBeTrue()
        ->and($this->schema->refresh()->folder_id)->toBe($this->nieuws->id)
        ->and($this->training->refresh()->folder_id)->toBe($this->nieuws->id)
        ->and($this->teamfoto->refresh()->folder_id)->toBe($this->teams->id);

    // Klaar stops selecting; with nothing selected the side column has no file.
    $page->click('[data-slot="details-selection-stop"]')
        ->assertAttribute('[data-slot="media-select"]', 'aria-pressed', 'false')
        ->assertVisible('[data-slot="details-file"]')
        ->assertNoJavaScriptErrors();
});

it('renames the open folder, which the chip, the trail and the upload field follow', function (): void {
    $page = visit('/made-cms/media?folder='.$this->teams->id)->resize(1280, 1100);

    expect($page->script(waitUntil(libraryShows(['wedstrijdschema', 'training-dinsdag', 'teamfoto-merwestad-1']))))->toBeTrue();

    $page->click('[data-slot="media-rename"]')
        ->assertMissing('[data-slot="media-folder-actions"]')
        ->assertSeeIn('[data-slot="media-folder-label"]', 'Nieuwe naam voor Teams')
        ->assertValue('[data-slot="media-folder-input"]', 'Teams')
        ->type('[data-slot="media-folder-input"]', 'nieuws')
        ->click('[data-slot="media-folder-confirm"]');

    expect($page->script(waitUntil("document.querySelector('[data-slot=\"media-folder-hint\"]').textContent === 'Er is al een map Nieuws.'")))->toBeTrue();

    $page->type('[data-slot="media-folder-input"]', 'Seniorenteams')
        ->keys('[data-slot="media-folder-input"]', 'Enter');

    expect($page->script(waitUntil(libraryNotice()." === 'De map heet nu Seniorenteams'")))->toBeTrue()
        ->and($page->script(waitUntil("document.querySelector('[data-media] .kmap[data-folder=\"{$this->teams->id}\"] .kmap-naam')?.textContent === 'Seniorenteams'")))->toBeTrue()
        ->and($page->script(libraryFolders()))->toBe([['Nieuws', '1', 'false'], ['Seniorenteams', '3', 'true']])
        ->and($page->script('() => location.search'))->toBe('?folder='.$this->teams->id)
        ->and($this->teams->refresh()->name)->toBe('Seniorenteams');

    $page->assertSeeIn('[data-slot="media-trail-here"]', 'Seniorenteams')
        // The upload field is folded away until files come in; its line already names the folder.
        ->assertScript("document.querySelector('[data-slot=\"upload-rules\"]').textContent.includes('de map Seniorenteams')")
        ->assertVisible('[data-slot="media-folder-actions"]')
        ->screenshot(filename: 'media-library-renamed')
        ->assertNoJavaScriptErrors();
});

it('keeps the alt text the editor typed when the folder of the chosen file gets a new name', function (): void {
    $page = visit('/made-cms/media?folder='.$this->teams->id.'&file='.$this->teamfoto->id)->resize(1280, 1100);

    expect($page->script(waitUntil(libraryDetails('teamfoto-merwestad-1.webp'))))->toBeTrue();

    $page->type('[data-slot="details-alt"]', 'Het team aan de tafels')
        ->assertVisible('[data-slot="details-changed"]')
        ->click('[data-slot="media-rename"]')
        ->type('[data-slot="media-folder-input"]', 'Seniorenteams')
        ->keys('[data-slot="media-folder-input"]', 'Enter');

    // The line follows the new name; the typed text stays, still to be stored.
    expect($page->script(waitUntil(libraryNotice()." === 'De map heet nu Seniorenteams'")))->toBeTrue()
        ->and($page->script(waitUntil("document.querySelector('[data-slot=\"details-line\"]').textContent.includes('map Seniorenteams')")))->toBeTrue()
        ->and($this->teamfoto->refresh()->alt)->toBe('Het eerste team voor de tafels');

    $page->assertValue('[data-slot="details-alt"]', 'Het team aan de tafels')
        ->assertVisible('[data-slot="details-changed"]')
        ->assertEnabled('[data-slot="details-save"]')
        ->click('[data-slot="details-save"]');

    expect($page->script(waitUntil("document.querySelector('[data-slot=\"details-status\"]').textContent !== ''")))->toBeTrue()
        ->and($this->teamfoto->refresh()->alt)->toBe('Het team aan de tafels');

    $page->assertNoJavaScriptErrors();
});

it('keeps the selection while files come in, and empties it for another folder', function (): void {
    $page = visit('/made-cms/media?folder='.$this->teams->id)->resize(1280, 1100);

    expect($page->script(waitUntil(libraryShows(['wedstrijdschema', 'training-dinsdag', 'teamfoto-merwestad-1']))))->toBeTrue();

    $page->click('[data-slot="media-select"]')
        ->keys(libraryTile($this->training), 'Space')
        ->keys(libraryTile($this->schema), 'Space')
        ->assertSeeIn('[data-slot="details-selection-count"]', '2 bestanden gekozen');

    $page->script(dropOnLibrary([['name' => 'Inschrijfformulier.pdf', 'type' => 'application/pdf', 'path' => mediaFixture('wedstrijdschema.pdf')]], drop: true));

    expect($page->script(waitUntil(libraryShows(['inschrijfformulier', 'wedstrijdschema', 'training-dinsdag', 'teamfoto-merwestad-1']))))->toBeTrue();

    $page->assertSeeIn('[data-slot="details-selection-count"]', '2 bestanden gekozen')
        ->assertSeeIn('[data-slot="details-selection-names"]', 'training-dinsdag.webp, wedstrijdschema.pdf')
        ->assertAttribute(libraryTile($this->training), 'aria-pressed', 'true')
        ->assertAttribute(libraryTile($this->schema), 'aria-pressed', 'true')
        ->assertAttribute(libraryTile($this->teamfoto), 'aria-pressed', 'false')
        ->click('[data-media] .kmap[data-folder="'.$this->nieuws->id.'"]');

    expect($page->script(waitUntil(libraryShows(['clubavond']))))->toBeTrue();

    $page->assertSeeIn('[data-slot="details-selection-count"]', 'Nog niets gekozen')
        ->assertAttribute('[data-slot="media-select"]', 'aria-pressed', 'true')
        ->assertNoJavaScriptErrors();
});

it('deletes the open folder only when it is empty', function (): void {
    $page = visit('/made-cms/media?folder='.$this->teams->id)->resize(1280, 1100);

    expect($page->script(waitUntil(libraryShows(['wedstrijdschema', 'training-dinsdag', 'teamfoto-merwestad-1']))))->toBeTrue();

    $page->click('[data-slot="media-delete"]');

    expect($page->script(waitUntil(libraryNotice()." === 'Teams heeft nog 3 bestanden. Zet ze eerst in een andere map.'")))->toBeTrue()
        ->and(MediaFolder::query()->whereKey($this->teams->id)->exists())->toBeTrue();

    $page->assertSeeIn('[data-slot="media-trail-here"]', 'Teams')
        ->screenshot(filename: 'media-library-folder-full');

    $jeugd = MediaFolder::factory()->create(['name' => 'Jeugd']);
    $page = visit('/made-cms/media?folder='.$jeugd->id)->resize(1280, 1100);

    expect($page->script(waitUntil(libraryShows([]))))->toBeTrue();

    $page->assertSeeIn('[data-slot="media-empty-title"]', 'Deze map is nog leeg')
        ->click('[data-slot="media-delete"]');

    expect($page->script(waitUntil(libraryNotice()." === 'Map Jeugd verwijderd'")))->toBeTrue()
        ->and($page->script(waitUntil(libraryShows(['wedstrijdschema', 'clubavond', 'training-dinsdag', 'teamfoto-merwestad-1']))))->toBeTrue()
        ->and($page->script(waitUntil("document.querySelectorAll('[data-media] li[data-folder-item]').length === 2")))->toBeTrue()
        ->and($page->script('() => location.search'))->toBe('')
        ->and(MediaFolder::query()->whereKey($jeugd->id)->exists())->toBeFalse();

    $page->assertSeeIn('[data-slot="media-trail-here"]', 'Alle bestanden')
        ->assertMissing('[data-slot="media-folder-actions"]')
        ->assertNoJavaScriptErrors();
});

it('shows the folders, the selection and a drag in the dark colours too', function (): void {
    $page = visit('/made-cms/media?folder='.$this->teams->id)->inDarkMode()->resize(1280, 1100);

    expect($page->script(waitUntil(libraryShows(['wedstrijdschema', 'training-dinsdag', 'teamfoto-merwestad-1']))))->toBeTrue();

    $page->click('[data-slot="media-select"]')
        ->keys(libraryTile($this->training), 'Space')
        ->click('[data-slot="media-rename"]')
        ->assertAttribute('[data-slot="media-select"]', 'aria-pressed', 'true');

    // The buttons fade to their colour.
    expect($page->script(waitUntil("getComputedStyle(document.querySelector('[data-slot=\"media-select\"]')).backgroundColor === 'rgb(217, 166, 58)'")))->toBeTrue()
        ->and($page->script("() => getComputedStyle(document.querySelector('".libraryTile($this->training)." .tegel-vakje')).backgroundColor"))->toBe('rgb(190, 138, 28)');

    $page->screenshot(filename: 'media-library-folders-dark')
        ->assertNoJavaScriptErrors();
});

it('manages folders and selects files by tapping on a phone', function (): void {
    $page = visit('/made-cms/media?folder='.$this->teams->id)->resize(390, 844);

    expect($page->script(waitUntil(libraryShows(['wedstrijdschema', 'training-dinsdag', 'teamfoto-merwestad-1']))))->toBeTrue();

    $page->click('[data-slot="media-rename"]')
        ->assertVisible('[data-slot="media-folder-field"]')
        ->screenshot(filename: 'media-library-phone-rename')
        ->click('[data-slot="media-folder-cancel"]')
        ->click('[data-slot="media-select"]')
        ->click(libraryTile($this->training))
        ->click(libraryTile($this->teamfoto))
        ->assertSeeIn('[data-slot="details-selection-count"]', '2 bestanden gekozen');

    // The column with the selection stays in view at the foot of the screen; Shift is no key on a phone.
    expect($page->script("() => getComputedStyle(document.querySelector('[data-media-details]')).position"))->toBe('sticky')
        ->and($page->script("() => getComputedStyle(document.querySelector('[data-slot=\"details-selection-hint\"] .niet-smal')).display"))->toBe('none')
        ->and($page->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $page->screenshot(filename: 'media-library-phone-selection')
        ->assertNoJavaScriptErrors();
});
