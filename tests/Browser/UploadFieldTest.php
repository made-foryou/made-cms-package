<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost']);
    Storage::fake('public');
    useWorkbenchSite();

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * A script for $page->script() that lets files hang above the upload field and, with $drop, lets them go. A file is
 * given by its path, or by a number of empty bytes for one that is too large to carry along.
 *
 * @param  list<array{name: string, type: string, path?: string, bytes?: int}>  $files
 */
function dragFiles(array $files, bool $drop): string
{
    $specs = json_encode(array_map(static fn (array $file): array => [
        'name' => $file['name'],
        'type' => $file['type'],
        'data' => isset($file['path']) ? base64_encode((string) file_get_contents($file['path'])) : null,
        'bytes' => $file['bytes'] ?? 0,
    ], $files), JSON_THROW_ON_ERROR);
    $events = json_encode($drop ? ['dragenter', 'dragover', 'drop'] : ['dragenter', 'dragover'], JSON_THROW_ON_ERROR);

    return <<<JS
        () => {
            const transfer = new DataTransfer();

            for (const spec of {$specs}) {
                const bytes = spec.data === null ? new Uint8Array(spec.bytes) : Uint8Array.from(atob(spec.data), (character) => character.charCodeAt(0));

                transfer.items.add(new File([bytes], spec.name, { type: spec.type }));
            }

            for (const type of {$events}) {
                document.querySelector('[data-upload]').dispatchEvent(new DragEvent(type, { bubbles: true, cancelable: true, dataTransfer: transfer }));
            }

            return transfer.files.length;
        }
        JS;
}

/**
 * A JavaScript condition: the upload field is in this moment (rest, drag, busy, done or error).
 */
function uploadMoment(string $moment): string
{
    return "document.querySelector('[data-upload]').dataset.moment === '{$moment}'";
}

/**
 * A script for $page->script(): what every file of the field shows, in the order of the list.
 */
function uploadRows(): string
{
    return <<<'JS'
        () => [...document.querySelectorAll('[data-upload] [data-slot="upload-list"] > li')].map((row) => {
            const shown = (slot) => {
                const element = row.querySelector('[data-slot="upload-row-' + slot + '"]');

                return element !== null && ! element.hidden;
            };

            return {
                state: row.dataset.state,
                name: row.querySelector('[data-slot="upload-row-name"]').textContent,
                note: row.querySelector('[data-slot="upload-row-note"]').textContent,
                buttons: ['cancel', 'retry', 'dismiss'].filter(shown),
                alt: shown('alt'),
            };
        })
        JS;
}

it('takes the good files of a drop and keeps the error of the one that is too large until it is left out', function (): void {
    config(['made-cms-package.media.max_upload_kb' => 1024]);
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    $files = [
        ['name' => 'Teamfoto Merwestad 4.jpg', 'type' => 'image/jpeg', 'path' => photo(2400, 1600)],
        ['name' => 'finale-2026.mov', 'type' => 'video/quicktime', 'bytes' => 1_200_000],
        ['name' => 'Wedstrijdschema najaar.pdf', 'type' => 'application/pdf', 'path' => mediaFixture('wedstrijdschema.pdf')],
    ];

    $page = visit('/werkbank/uploadveld?folder='.$folder->id);

    $page->assertSeeIn('[data-slot="upload-title"]', 'Sleep je bestanden hierheen')
        ->assertSeeIn('[data-slot="upload-rules"]', 'Foto’s, documenten en video’s · tot 1 MB per bestand · komen in de map Teams')
        ->assertMissing('[data-slot="upload-list"]');

    expect($page->script(dragFiles($files, drop: false)))->toBe(3)
        ->and($page->script(waitUntil(uploadMoment('drag'))))->toBeTrue();

    $page->assertSeeIn('[data-slot="upload-drag-title"]', 'Laat maar los')
        ->assertSeeIn('[data-slot="upload-drag-line"]', '3 bestanden · ze komen in de map Teams')
        ->assertMissing('[data-slot="upload-title"]');

    $page->script(dragFiles($files, drop: true));

    expect($page->script(waitUntil(uploadMoment('done'))))->toBeTrue();

    $photo = Media::query()->where('name', 'teamfoto-merwestad-4')->sole();
    $rows = $page->script(uploadRows());

    expect(Media::query()->count())->toBe(2)
        ->and(Media::query()->pluck('folder_id')->unique()->all())->toBe([$folder->id])
        ->and($rows[0])->toMatchArray(['state' => 'done', 'name' => 'teamfoto-merwestad-4.webp', 'buttons' => [], 'alt' => true])
        ->and($rows[0]['note'])->toStartWith('1920 × 1280 · ')->toContain(' · was ')
        ->and($rows[1])->toBe([
            'state' => 'error',
            'name' => 'finale-2026.mov · 1,2 MB',
            'note' => 'Te groot: een bestand mag 1 MB zijn. Zet de video op YouTube of Vimeo en plak de link in een videoblok; dat laadt voor bezoekers ook sneller.',
            'buttons' => ['dismiss'],
            'alt' => false,
        ])
        ->and($rows[2])->toBe([
            'state' => 'done',
            'name' => 'wedstrijdschema-najaar.pdf',
            'note' => '1 kB · geen afbeelding, dus geen alt-tekst nodig',
            'buttons' => [],
            'alt' => false,
        ]);

    $page->assertSeeIn('[data-slot="upload-done-text"]', '2 bestanden toegevoegd aan de map Teams')
        ->assertSeeIn('[data-slot="upload-errors-note"]', 'De andere bestanden zijn gewoon doorgegaan.')
        ->assertVisible('[data-slot="upload-save"]')
        ->click('[data-slot="upload-later"]');

    // The files are in the library; the error stays until the editor leaves the file out.
    expect($page->script(waitUntil(uploadMoment('error'))))->toBeTrue()
        ->and(array_column($page->script(uploadRows()), 'name'))->toBe(['finale-2026.mov · 1,2 MB'])
        ->and($photo->refresh()->alt)->toBeNull();

    $page->click('[data-slot="upload-row-dismiss"]');

    expect($page->script(waitUntil(uploadMoment('rest'))))->toBeTrue();

    $page->assertMissing('[data-slot="upload-list"]')
        ->assertMissing('[data-slot="upload-errors-note"]')
        ->assertVisible('[data-slot="upload-title"]')
        ->assertNoJavaScriptErrors();
});

it('works with the keyboard and the file chooser, and saves the alt text the editor types', function (): void {
    $page = visit('/werkbank/uploadveld');

    // Enter on "kies ze op je computer" opens the file chooser of the browser.
    $page->script(<<<'JS'
        () => {
            window.uploadEvents = [];
            ['made-cms:upload', 'made-cms:uploaded', 'made-cms:upload-closed'].forEach((name) => {
                document.addEventListener(name, (event) => window.uploadEvents.push({ name, detail: event.detail }));
            });
            document.querySelector('[data-slot="upload-input"]').addEventListener('click', (event) => {
                event.preventDefault();
                window.chooserOpened = true;
            });
        }
        JS);
    $page->keys('button[data-slot="upload-choose"]', 'Enter');

    expect($page->script('() => window.chooserOpened === true'))->toBeTrue();

    $page->attach('[data-slot="upload-input"]', photo(2400, 1600, 'jpg', 'Clubavond oktober.jpg'));

    expect($page->script(waitUntil(uploadMoment('done'))))->toBeTrue();

    $media = Media::query()->sole();

    $page->assertSeeIn('[data-slot="upload-done-text"]', '1 bestand toegevoegd aan Alle bestanden')
        ->assertSeeIn('[data-slot="upload-row-alt-label"]', 'Alt-tekst')
        ->assertAttribute('[data-slot="upload-row-bar"]', 'role', 'progressbar')
        ->assertAttribute('[data-slot="upload-row-bar"]', 'aria-valuenow', '100')
        ->assertAttribute('[data-slot="upload-row-bar"]', 'aria-label', 'Clubavond oktober.jpg uploaden')
        ->assertSeeIn('[data-slot="upload-announce"]', '1 bestand toegevoegd aan Alle bestanden')
        ->type('[data-slot="upload-row-alt-input"]', 'Leden aan de bar na de clubavond in oktober')
        ->keys('[data-slot="upload-row-alt-input"]', 'Enter');

    expect($page->script(waitUntil(uploadMoment('rest'))))->toBeTrue()
        ->and($media->refresh()->alt)->toBe('Leden aan de bar na de clubavond in oktober');

    $events = $page->script('() => window.uploadEvents');
    $uploaded = array_values(array_filter($events, static fn (array $event): bool => $event['name'] === 'made-cms:uploaded'));
    $closed = array_values(array_filter($events, static fn (array $event): bool => $event['name'] === 'made-cms:upload-closed'));

    expect($uploaded)->toHaveCount(1)
        ->and($uploaded[0]['detail']['file']['id'])->toBe($media->id)
        ->and($closed)->toHaveCount(1)
        ->and($closed[0]['detail']['files'][0]['alt'])->toBe('Leden aan de bar na de clubavond in oktober')
        ->and($page->script('() => document.activeElement?.dataset.slot'))->toBe('upload-choose');

    $page->assertSeeIn('[data-slot="upload-announce"]', 'De alt-teksten zijn opgeslagen.')
        ->assertNoJavaScriptErrors();
});

it('uploads one file at a time, shows its progress and lets a waiting file be cancelled', function (): void {
    $page = visit('/werkbank/uploadveld');

    // The first request is held, so the field stays in the moment "busy" for as long as the test looks.
    $page->script(<<<'JS'
        () => {
            const send = XMLHttpRequest.prototype.send;

            window.sent = 0;
            XMLHttpRequest.prototype.send = function (...body) {
                window.sent++;
                window.release = () => {
                    XMLHttpRequest.prototype.send = send;
                    send.apply(this, body);
                };
            };
        }
        JS);
    $page->script(dragFiles([
        ['name' => 'teamfoto-merwestad-4.jpg', 'type' => 'image/jpeg', 'path' => photo(600, 400)],
        ['name' => 'wedstrijdschema-najaar.pdf', 'type' => 'application/pdf', 'path' => mediaFixture('wedstrijdschema.pdf')],
    ], drop: true));

    expect($page->script(waitUntil(uploadMoment('busy'))))->toBeTrue()
        ->and($page->script('() => window.sent'))->toBe(1);

    $rows = $page->script(uploadRows());

    expect($rows[0])->toMatchArray(['state' => 'uploading', 'name' => 'teamfoto-merwestad-4.jpg', 'buttons' => []])
        ->and($rows[1])->toBe([
            'state' => 'waiting',
            'name' => 'wedstrijdschema-najaar.pdf',
            'note' => 'Wacht · 1 kB',
            'buttons' => ['cancel'],
            'alt' => false,
        ]);

    $page->assertVisible('li[data-state="uploading"] [role="progressbar"]')
        ->assertSeeIn('li[data-state="uploading"] [data-slot="upload-row-percent"]', '0%')
        ->assertSeeIn('[data-slot="upload-summary"]', '0 van 2 klaar. Je kunt ondertussen doorwerken.')
        ->assertMissing('[data-slot="upload-actions"]')
        ->click('li[data-state="waiting"] [data-slot="upload-row-cancel"]');

    $page->assertSeeIn('[data-slot="upload-summary"]', '0 van 1 klaar. Je kunt ondertussen doorwerken.');
    $page->script('() => window.release()');

    expect($page->script(waitUntil(uploadMoment('done'))))->toBeTrue()
        ->and(Media::query()->pluck('name')->all())->toBe(['teamfoto-merwestad-4'])
        ->and($page->script('() => window.sent'))->toBe(1);

    $page->assertNoJavaScriptErrors();
});

it('shows what the server refuses with the file and reads it out', function (): void {
    $page = visit('/werkbank/uploadveld');

    $page->attach('[data-slot="upload-input"]', uploadable('logo-nieuw.psd', "8BPS\x00\x01".str_repeat("\x00", 40)));

    expect($page->script(waitUntil(uploadMoment('error'))))->toBeTrue()
        ->and($page->script(uploadRows()))->toBe([[
            'state' => 'error',
            'name' => 'logo-nieuw.psd · 1 kB',
            'note' => 'Dit type kent de site niet. Sla het op als jpg, png, webp, gif, svg, pdf of mp4 en probeer het opnieuw.',
            'buttons' => ['dismiss'],
            'alt' => false,
        ]])
        ->and(Media::query()->count())->toBe(0);

    $page->assertSeeIn('[data-slot="upload-announce"]', 'logo-nieuw.psd: Dit type kent de site niet.')
        ->assertAttribute('[data-slot="upload-announce"]', 'aria-live', 'polite')
        ->assertNoJavaScriptErrors();
});

it('keeps a file whose connection dropped and uploads it when the editor tries again', function (): void {
    $page = visit('/werkbank/uploadveld');

    $page->offline()->attach('[data-slot="upload-input"]', mediaFixture('wedstrijdschema.pdf'));

    expect($page->script(waitUntil(uploadMoment('error'))))->toBeTrue();

    $row = $page->script(uploadRows())[0];

    expect($row['buttons'])->toBe(['retry', 'dismiss'])
        ->and($row['note'])->toMatch('/^De verbinding viel weg bij \d+%\. Niets is kwijt; probeer het nog eens\.$/')
        ->and(Media::query()->count())->toBe(0);

    $page->online()->click('[data-slot="upload-row-retry"]');

    expect($page->script(waitUntil(uploadMoment('done'))))->toBeTrue()
        ->and(Media::query()->sole()->name)->toBe('wedstrijdschema')
        ->and($page->script(uploadRows())[0]['state'])->toBe('done');

    // A document needs no alt text: the field closes with one button.
    $page->assertVisible('[data-slot="upload-close"]')
        ->assertMissing('[data-slot="upload-save"]')
        ->click('[data-slot="upload-close"]');

    expect($page->script(waitUntil(uploadMoment('rest'))))->toBeTrue();

    $page->assertNoJavaScriptErrors();
});

it('shows an alt text the server refuses with its file', function (): void {
    $page = visit('/werkbank/uploadveld');

    $page->attach('[data-slot="upload-input"]', photo(600, 400));

    expect($page->script(waitUntil(uploadMoment('done'))))->toBeTrue();

    // The field stops at 255 characters itself; a longer text only arrives when something else puts it there.
    $page->script("() => { const input = document.querySelector('[data-slot=\"upload-row-alt-input\"]'); input.removeAttribute('maxlength'); input.value = 'a'.repeat(256); }");
    $page->click('[data-slot="upload-save"]');

    expect($page->script(waitUntil("! document.querySelector('[data-slot=\"upload-row-alt-error\"]').hidden")))->toBeTrue()
        ->and(Media::query()->sole()->alt)->toBeNull();

    $page->assertSeeIn('[data-slot="upload-row-alt-error"]', 'Een alt-tekst mag hoogstens 255 tekens zijn.')
        ->assertAttribute('[data-slot="upload-row-alt-input"]', 'aria-invalid', '')
        ->assertVisible('[data-slot="upload-save"]')
        ->assertNoJavaScriptErrors();
});

it('follows the dark colours of the system', function (): void {
    $page = visit('/werkbank/uploadveld')->inDarkMode();

    $page->attach('[data-slot="upload-input"]', mediaFixture('wedstrijdschema.pdf'));

    expect($page->script(waitUntil(uploadMoment('done'))))->toBeTrue()
        ->and($page->script(<<<'JS'
            () => {
                const colour = (selector, property) => getComputedStyle(document.querySelector(selector))[property];

                return [
                    colour('body', 'backgroundColor'),
                    colour('.upload-rij', 'backgroundColor'),
                    colour('.upload-titel', 'color'),
                    colour('.upload-vinkje', 'backgroundColor'),
                ];
            }
            JS))->toBe(['rgb(14, 43, 38)', 'rgb(27, 61, 54)', 'rgb(243, 241, 232)', 'rgb(69, 168, 118)']);

    $page->assertNoJavaScriptErrors();
});
