<?php

declare(strict_types=1);

use Dom\HTMLDocument;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Http\Cms\UploadField;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;

/**
 * The field as a host includes it.
 *
 * @param  array<string, mixed>  $upload
 */
function uploadFieldHtml(array $upload): string
{
    return view('made-cms-package-launcher::cms.partials.upload', ['upload' => $upload])->render();
}

it('tells the script where to send a file and its alt text, and what the library takes', function (): void {
    $config = UploadField::make()['config'];

    expect($config['urls'])->toBe(['upload' => '/made-cms/media', 'alt' => '/made-cms/media/:media/alt'])
        ->and($config)->toHaveKey('csrf_token')
        ->and($config['folder'])->toBe(['id' => null, 'label' => 'Alle bestanden'])
        ->and($config['limits'])->toBe(['max_bytes' => 25600 * 1024]);
});

it('sends files into the folder the host gives', function (): void {
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);

    $upload = UploadField::make($folder);

    expect($upload['config']['folder'])->toBe(['id' => $folder->id, 'label' => 'de map Teams'])
        ->and($upload['rules'])->toBe('Foto’s, documenten en video’s · tot 25 MB per bestand · komen in de map Teams');
});

it('gives the script its texts as plain strings, the limits already filled in', function (): void {
    config(['made-cms-package.media.max_upload_kb' => 4096]);

    $texts = UploadField::make()['config']['texts'];

    expect($texts)->each->toBeString()
        ->and($texts)->toMatchArray([
            'drag_many' => ':count bestanden · ze komen in :folder',
            'summary' => ':done van :total klaar. Je kunt ondertussen doorwerken.',
            'processing_image' => 'Geüpload · wordt verkleind naar webp, max 1920 px',
            'too_large' => 'Te groot: een bestand mag 4 MB zijn.',
            'too_large_video' => 'Te groot: een bestand mag 4 MB zijn. Zet de video op YouTube of Vimeo en plak de link in een videoblok; dat laadt voor bezoekers ook sneller.',
            'kb' => ':size kB',
            'mb' => ':size MB',
            'decimal' => ',',
        ])
        ->and($texts['server_limit'])->toStartWith('De server neemt dit bestand niet aan: hij staat op hoogstens ');
});

it('names the kinds of file the site takes', function (array $mimes, string $rules): void {
    config(['made-cms-package.media.allowed_mimes' => $mimes]);

    expect(UploadField::make()['rules'])->toBe($rules);
})->with([
    'only photos' => [['image/jpeg', 'image/png'], 'Foto’s · tot 25 MB per bestand · komen in Alle bestanden'],
    'photos and documents' => [['image/jpeg', 'application/pdf'], 'Foto’s en documenten · tot 25 MB per bestand · komen in Alle bestanden'],
]);

it('lets the file chooser offer the types the library takes', function (): void {
    config(['made-cms-package.media.allowed_mimes' => ['image/jpeg', 'application/pdf']]);

    expect(UploadField::make()['accept'])->toBe('image/jpeg,application/pdf');
});

it('points the host at the script of the field', function (): void {
    expect(UploadField::make()['script'])->toBe(LauncherAssetController::url('upload.js'));
});

it('renders the field with its fixed texts, a file chooser a keyboard reaches and its config', function (): void {
    $upload = UploadField::make(MediaFolder::factory()->create(['name' => 'Teams & <b>meer</b>']));

    $html = uploadFieldHtml($upload);
    $document = HTMLDocument::createFromString('<!doctype html><body>'.$html, LIBXML_NOERROR);
    $field = $document->querySelector('[data-upload]');

    expect($field)->not->toBeNull()
        ->and($field?->getAttribute('aria-label'))->toBe('Bestanden uploaden')
        ->and(json_decode((string) $field?->getAttribute('data-config'), true))->toBe($upload['config'])
        ->and($document->querySelector('[data-slot="upload-title"]')?->textContent)->toBe('Sleep je bestanden hierheen')
        ->and($document->querySelector('button[data-slot="upload-choose"]')?->textContent)->toBe('kies ze op je computer')
        ->and($document->querySelector('[data-slot="upload-rules"]')?->textContent)->toBe('Foto’s, documenten en video’s · tot 25 MB per bestand · komen in de map Teams & <b>meer</b>')
        ->and($document->querySelector('input[type="file"][data-slot="upload-input"]')?->hasAttribute('multiple'))->toBeTrue()
        ->and($field?->hasAttribute('data-one-image'))->toBeFalse()
        ->and($document->querySelector('[data-slot="upload-announce"]')?->getAttribute('aria-live'))->toBe('polite')
        ->and($document->querySelector('[data-slot="upload-summary"]')?->getAttribute('role'))->toBe('status')
        ->and($html)->toContain('role="progressbar"')
        ->and($html)->not->toContain('<b>meer</b>');
});

it('follows the language of the launcher', function (): void {
    config(['made-cms-package.launcher.locale' => 'en']);

    $upload = UploadField::make();

    expect($upload['rules'])->toBe('Photos, documents and videos · up to 25 MB per file · go into All files')
        ->and(uploadFieldHtml($upload))->toContain('Drag your files here');
});

it('takes one photo for an image field, and says so in the singular', function (): void {
    config(['made-cms-package.media.allowed_mimes' => ['image/jpeg', 'image/svg+xml', 'application/pdf', 'video/mp4']]);

    $upload = UploadField::image();

    expect($upload['one_image'])->toBeTrue()
        ->and($upload['accept'])->toBe('image/jpeg,image/svg+xml')
        ->and($upload['rules'])->toBe('Foto’s · tot 25 MB · komt in Alle bestanden')
        ->and($upload['config']['one_image'])->toBeTrue()
        ->and($upload['config']['folder'])->toBe(['id' => null, 'label' => 'Alle bestanden'])
        ->and($upload['config']['texts'])->toMatchArray([
            'not_image' => 'Dit veld neemt alleen foto’s. Dit bestand is niet geüpload; sleep een foto hierheen, of kies er een op je computer.',
            'drag_many' => 'Eén foto tegelijk: de eerste komt in :folder',
            'drag_unknown' => 'Hij komt in :folder',
            'done_one' => '1 bestand toegevoegd aan :folder',
        ])
        ->and(UploadField::make()['one_image'])->toBeFalse()
        ->and(UploadField::make()['config']['one_image'])->toBeFalse();
});

it('renders the field for one photo without a choice of several files', function (): void {
    $upload = UploadField::image(MediaFolder::factory()->create(['name' => 'Teams']));

    $html = uploadFieldHtml($upload);
    $document = HTMLDocument::createFromString('<!doctype html><body>'.$html, LIBXML_NOERROR);

    expect($document->querySelector('[data-upload]')?->getAttribute('aria-label'))->toBe('Foto uploaden')
        ->and($document->querySelector('[data-slot="upload-title"]')?->textContent)->toBe('Sleep je foto hierheen')
        ->and($document->querySelector('button[data-slot="upload-choose"]')?->textContent)->toBe('kies hem op je computer')
        ->and($document->querySelector('[data-slot="upload-rules"]')?->textContent)->toBe('Foto’s · tot 25 MB · komt in de map Teams')
        ->and($document->querySelector('[data-slot="upload-save"]')?->textContent)->toBe('Alt-tekst opslaan')
        ->and($document->querySelector('[data-slot="upload-later-hint"]')?->textContent)->toBe('Later doen kan: de foto houdt dan een amber stip, hier en in Media.')
        ->and($document->querySelector('[data-upload]')?->hasAttribute('data-one-image'))->toBeTrue()
        ->and($document->querySelector('input[type="file"][data-slot="upload-input"]')?->hasAttribute('multiple'))->toBeFalse()
        ->and($document->querySelector('input[type="file"][data-slot="upload-input"]')?->getAttribute('accept'))->toBe($upload['accept']);
});

it('takes one photo in the language of the launcher too', function (): void {
    config(['made-cms-package.launcher.locale' => 'en']);

    $upload = UploadField::image();

    expect($upload['rules'])->toBe('Photos · up to 25 MB · goes into All files')
        ->and($upload['config']['texts']['not_image'])->toStartWith('This field only takes photos.')
        ->and(uploadFieldHtml($upload))->toContain('Drag your photo here');
});
