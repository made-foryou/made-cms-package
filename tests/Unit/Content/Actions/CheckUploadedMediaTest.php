<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Content\Actions\CheckUploadedMedia;
use Made\CmsPackage\Content\Data\CheckedMedia;
use Made\CmsPackage\Content\Enums\MediaKind;

function uploadRefusal(string $path): ?string
{
    try {
        CheckUploadedMedia::run($path);

        return null;
    } catch (ValidationException $exception) {
        return $exception->errors()['file'][0] ?? '';
    }
}

it('reads the type, kind, extension and size from the contents', function (Closure $file, string $mime, MediaKind $kind, string $extension): void {
    $path = $file();

    $checked = CheckUploadedMedia::run($path);

    expect($checked)->toEqual(new CheckedMedia($mime, $kind, $extension, (int) filesize($path)));
})->with([
    'a jpg' => [fn (): string => photo(30, 20, 'jpg'), 'image/jpeg', MediaKind::Image, 'jpg'],
    'a png' => [fn (): string => photo(30, 20, 'png'), 'image/png', MediaKind::Image, 'png'],
    'a webp' => [fn (): string => photo(30, 20, 'webp'), 'image/webp', MediaKind::Image, 'webp'],
    'a gif' => [fn (): string => photo(30, 20, 'gif'), 'image/gif', MediaKind::Image, 'gif'],
    'an svg' => [fn (): string => mediaFixture('logo.svg'), 'image/svg+xml', MediaKind::Image, 'svg'],
    'a pdf' => [fn (): string => mediaFixture('wedstrijdschema.pdf'), 'application/pdf', MediaKind::Document, 'pdf'],
    'an mp4' => [fn (): string => mediaFixture('samenvatting.mp4'), 'video/mp4', MediaKind::Video, 'mp4'],
]);

it('goes by the contents, never by the name', function (): void {
    expect(CheckUploadedMedia::run(photo(30, 20, 'png', 'foto.jpg'))->mime)->toBe('image/png')
        ->and(uploadRefusal(uploadable('foto.jpg', 'gewoon tekst')))->toBe(__('made-cms-package::media.errors.type_unknown', ['types' => 'jpg, png, webp, gif, svg, pdf of mp4']));
});

it('knows an svg that starts with a comment or white space', function (string $before): void {
    $path = uploadable('logo.svg', $before.'<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>');

    expect(CheckUploadedMedia::run($path)->mime)->toBe('image/svg+xml');
})->with(['a comment' => ["<!-- Generator: Adobe Illustrator -->\n"], 'white space' => ["\n\n   "]]);

it('refuses a type the site does not take, in the words of the design', function (): void {
    expect(uploadRefusal(uploadable('logo-nieuw.psd', "8BPS\x00\x01".str_repeat("\x00", 40))))
        ->toBe('Dit type kent de site niet. Sla het op als jpg, png, webp, gif, svg, pdf of mp4 en probeer het opnieuw.');
});

it('refuses a type the site left out of its list', function (): void {
    config(['made-cms-package.media.allowed_mimes' => ['image/jpeg', 'image/png']]);

    expect(uploadRefusal(mediaFixture('wedstrijdschema.pdf')))
        ->toBe('Dit type kent de site niet. Sla het op als jpg of png en probeer het opnieuw.');
});

it('refuses an svg with active content', function (): void {
    $path = uploadable('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect/></svg>');

    expect(uploadRefusal($path))->toBe(__('made-cms-package::media.errors.svg_unsafe'));
});

it('takes a file up to the maximum and refuses one byte more', function (): void {
    config(['made-cms-package.media.max_upload_kb' => 1]);

    expect(uploadRefusal(uploadable('a.pdf', '%PDF-1.4'.str_repeat(' ', 1024 - 8))))->toBeNull()
        ->and(uploadRefusal(uploadable('b.pdf', '%PDF-1.4'.str_repeat(' ', 1025 - 8))))->toBe('Te groot: een bestand mag 1 kB zijn.');
});

it('says the maximum in megabytes when it is a whole number of them', function (): void {
    config(['made-cms-package.media.max_upload_kb' => 1024]);

    expect(uploadRefusal(uploadable('groot.pdf', '%PDF-1.4'.str_repeat(' ', 1024 * 1024))))->toBe('Te groot: een bestand mag 1 MB zijn.');
});

it('advises a video platform for a video that is too large, also one of a type the site does not take', function (string $header): void {
    config(['made-cms-package.media.max_upload_kb' => 1]);

    $path = uploadable('finale-2026.mov', pack('N', 20).$header.pack('N', 0).substr($header, 4).pack('N', 2048).'mdat'.str_repeat("\x00", 2048));

    expect(uploadRefusal($path))->toBe(
        'Te groot: een bestand mag 1 kB zijn. Zet de video op YouTube of Vimeo en plak de link in een videoblok; dat laadt voor bezoekers ook sneller.',
    );
})->with(['an mp4' => ['ftypmp42'], 'a mov' => ['ftypqt  ']]);

it('refuses in English for an English editor', function (): void {
    app()->setLocale('en');
    config(['made-cms-package.media.allowed_mimes' => ['image/jpeg']]);

    expect(uploadRefusal(mediaFixture('wedstrijdschema.pdf')))->toBe('The site does not know this type. Save it as jpg and try again.');
});

it('refuses a file that is not there', function (): void {
    expect(uploadRefusal(sys_get_temp_dir().'/made-cms-nothing-'.Str::random(8)))->toBe(__('made-cms-package::media.errors.unreadable'));
});
