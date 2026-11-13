<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;

beforeEach(function (): void {
    Storage::fake('public');
    $this->editor = Editor::factory()->create();
    $this->actingAs($this->editor, 'made-cms');
});

/**
 * A file as the browser sends it: the path of a file on this machine under the name the editor had it.
 */
function sent(string $path, ?string $name = null, int $error = UPLOAD_ERR_OK): UploadedFile
{
    return new UploadedFile($path, $name ?? basename($path), null, $error, true);
}

it('stores a photo scaled down and answers with the file as the field shows it', function (): void {
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    $photo = photo(2400, 1600, 'jpg', 'Teamfoto Merwestad 4.jpg');

    $response = $this->postJson('made-cms/media', ['file' => sent($photo), 'folder' => (string) $folder->id])
        ->assertCreated()
        ->assertHeader('Cache-Control', 'no-store, private');

    $media = Media::query()->sole();

    expect($media->folder_id)->toBe($folder->id)
        ->and($media->uploaded_by)->toBe($this->editor->id)
        ->and($media->width)->toBe(1920)
        ->and($response->json('file'))->toBe([
            'id' => $media->id,
            'url' => Storage::disk('public')->url($media->path),
            'thumb' => Storage::disk('public')->url((string) $media->thumb_path),
            'name' => 'teamfoto-merwestad-4',
            'extension' => 'webp',
            'meta' => '1920 × 1280 · '.max(1, (int) round($media->size / 1000)).' kB',
            'folder' => 'Teams',
            'alt' => null,
            'image' => [
                'url' => Storage::disk('public')->url($media->path),
                'alt' => '',
                'width' => 1920,
                'height' => 1280,
                'focal' => ['x' => 0.5, 'y' => 0.5],
            ],
            'croppable' => true,
            'kind' => 'image',
            'file' => 'teamfoto-merwestad-4.webp',
            'size' => $media->size,
        ])
        ->and($response->json('file.thumb'))->toMatch('#/media/[0-9a-z]{26}/thumb-[0-9A-Za-z]{40}\.webp$#')
        ->and((string) $response->getContent())->not->toContain('original');
});

it('stores a document as it came in, outside a folder and without an image', function (): void {
    $response = $this->postJson('made-cms/media', ['file' => sent(mediaFixture('wedstrijdschema.pdf', 'Wedstrijdschema najaar.pdf'))])
        ->assertCreated();

    $media = Media::query()->sole();

    expect($media->folder_id)->toBeNull()
        ->and($response->json('file.kind'))->toBe('document')
        ->and($response->json('file.file'))->toBe('wedstrijdschema-najaar.pdf')
        ->and($response->json('file.extension'))->toBe('pdf')
        ->and($response->json('file.croppable'))->toBeFalse()
        ->and($response->json('file.meta'))->toBe('1 kB')
        ->and($response->json('file.size'))->toBe(142)
        ->and($response->json('file.image'))->toBeNull()
        ->and($response->json('file.thumb'))->toBeNull();
});

it('shows an svg small as itself', function (): void {
    $response = $this->postJson('made-cms/media', ['file' => sent(mediaFixture('logo.svg'))])
        ->assertCreated();

    expect($response->json('file.thumb'))->toBe($response->json('file.url'))
        ->and($response->json('file.croppable'))->toBeFalse()
        ->and($response->json('file.thumb'))->toEndWith('/logo.svg');
});

it('refuses a file the library does not take, in the words of the check', function (Closure $file, string $message): void {
    config(['made-cms-package.media.max_upload_kb' => 4]);

    $this->postJson('made-cms/media', ['file' => sent($file())])
        ->assertUnprocessable()
        ->assertJsonPath('errors.file.0', $message);

    expect(Media::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'too large' => [
        fn (): string => uploadable('groot.pdf', '%PDF-1.4'.str_repeat(' ', 5000)),
        'Te groot: een bestand mag 4 kB zijn.',
    ],
    'a video that is too large' => [
        fn (): string => uploadable('finale.mp4', (string) file_get_contents(__DIR__.'/../../../../Fixtures/Media/samenvatting.mp4').str_repeat("\x00", 5000)),
        'Te groot: een bestand mag 4 kB zijn. Zet de video op YouTube of Vimeo en plak de link in een videoblok; dat laadt voor bezoekers ook sneller.',
    ],
    'an unknown type' => [
        fn (): string => uploadable('logo-nieuw.psd', "8BPS\x00\x01".str_repeat("\x00", 40)),
        'Dit type kent de site niet. Sla het op als jpg, png, webp, gif, svg, pdf of mp4 en probeer het opnieuw.',
    ],
    'an svg with a script' => [
        fn (): string => uploadable('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'Deze svg bevat code die de site niet toelaat, zoals een script of een link naar buiten. Exporteer hem opnieuw als gewone tekening en probeer het nog eens.',
    ],
]);

it('goes by the contents of the file, never by the name the browser gives', function (): void {
    $this->postJson('made-cms/media', ['file' => sent(uploadable('foto.jpg', 'gewoon tekst'))])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

it('refuses a request without one uploaded file', function (array $input): void {
    $this->postJson('made-cms/media', $input)
        ->assertUnprocessable()
        ->assertJsonPath('errors.file.0', 'Kies een bestand om te uploaden.');
})->with([
    'nothing' => [[]],
    'a text' => [['file' => 'teamfoto.jpg']],
    'more than one' => fn (): array => [['file' => [sent(photo(30, 20)), sent(photo(30, 20))]]],
]);

it('says that the server is the limit when php refused the upload', function (int $error): void {
    $this->postJson('made-cms/media', ['file' => sent(photo(30, 20), error: $error)])
        ->assertUnprocessable()
        ->assertJsonPath('errors.file.0', fn (string $message): bool => str_starts_with($message, 'De server neemt dit bestand niet aan: hij staat op hoogstens ')
            && preg_match('/hoogstens \d+ (MB|kB) per upload/', $message) === 1);

    expect(Media::query()->count())->toBe(0);
})->with(['upload_max_filesize' => [UPLOAD_ERR_INI_SIZE], 'a partial upload' => [UPLOAD_ERR_PARTIAL]]);

it('refuses a folder that is gone or is no folder', function (mixed $folder): void {
    $this->postJson('made-cms/media', ['file' => sent(photo(30, 20)), 'folder' => $folder])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('folder');

    expect(Media::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with(['gone' => [999], 'no number' => ['teams']]);

it('opens for every editor who opens the pages or the media library', function (Role $role): void {
    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->postJson('made-cms/media', ['file' => sent(photo(30, 20))])
        ->assertCreated();
})->with([Role::Owner, Role::SeoSpecialist, Role::ContentManager, Role::Admin]);

it('forbids it to an editor who opens neither', function (): void {
    $this->actingAs(Editor::factory()->withRoles()->create(), 'made-cms')
        ->postJson('made-cms/media', ['file' => sent(photo(30, 20))])
        ->assertForbidden();

    expect(Media::query()->count())->toBe(0);
});

it('answers 401 to a guest', function (): void {
    auth('made-cms')->logout();

    $this->postJson('made-cms/media', ['file' => sent(photo(30, 20))])->assertUnauthorized();

    expect(Media::query()->count())->toBe(0);
});

it('keeps the screen that is coming at the same address', function (): void {
    $this->get('made-cms/media')->assertOk()->assertSee('<h1 id="made-cms-titel">Media</h1>', false);
});
