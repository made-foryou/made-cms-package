<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Content\Data\MediaFiles;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Strips\Fields\File;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Tests\Fixtures\Strips\InlineStripType;

beforeEach(function (): void {
    config(['app.url' => 'https://example.com']);
});

function documentFile(int $id, MediaKind $kind = MediaKind::Document, ?string $alt = null, ?string $key = '01jbx3m5zq8w2p4r6t8v0x2z4b'): MediaFile
{
    $extension = $kind === MediaKind::Video ? 'mp4' : 'pdf';

    return new MediaFile(
        id: $id,
        kind: $kind,
        url: "https://example.com/storage/media/{$id}/wedstrijdschema.{$extension}",
        thumb: null,
        name: 'wedstrijdschema',
        mime: $kind === MediaKind::Video ? 'video/mp4' : 'application/pdf',
        size: 1_240_000,
        width: null,
        height: null,
        alt: $alt,
        focalX: null,
        focalY: null,
        folder: null,
        key: $key,
    );
}

it('is a file the side panel knows by its type and the kinds it takes', function (): void {
    $file = File::make('bestand')->label('Document')->hint('Een pdf.')->kinds(MediaKind::Document)->required();

    expect($file->type())->toBe('file')
        ->and($file->describe())->toBe([
            'key' => 'bestand',
            'type' => 'file',
            'label' => 'Document',
            'hint' => 'Een pdf.',
            'required' => true,
            'default' => null,
            'kinds' => ['document'],
        ]);
});

it('takes a document or a video unless it is told otherwise', function (): void {
    expect(File::make('bestand')->getKinds())->toBe([MediaKind::Document, MediaKind::Video]);
});

it('keeps its kinds once and in the order of the kinds, whatever order they are written in', function (): void {
    $file = File::make('bestand')->kinds(MediaKind::Video, MediaKind::Document, MediaKind::Video);

    expect($file->getKinds())->toBe([MediaKind::Document, MediaKind::Video])
        ->and($file->describe()['kinds'])->toBe(['document', 'video']);
});

it('gives a changed copy when its kinds change', function (): void {
    $file = File::make('bestand');

    expect($file->kinds(MediaKind::Video))->not->toBe($file)
        ->and($file->getKinds())->toBe([MediaKind::Document, MediaKind::Video]);
});

it('validates the id of a file as present or empty, and then as a file of the library', function (): void {
    $rules = File::make('bestand')->rules('bestand');
    $required = File::make('bestand')->required()->rules('bestand');

    expect(array_keys($rules))->toBe(['bestand'])
        ->and($rules['bestand'][0])->toBe('nullable')
        ->and($rules['bestand'][1])->toBeInstanceOf(Closure::class)
        ->and($required['bestand'][0])->toBe('required');
});

it('stores the id of a file as a number, and anything else as no file', function (mixed $value, ?int $stored): void {
    expect(File::make('bestand')->normalize($value))->toBe($stored);
})->with([
    'a number' => [12, 12],
    'a number as text' => ['12', 12],
    'nothing' => [null, null],
    'an empty text' => ['', null],
    'a text' => ['wedstrijdschema.pdf', null],
    'a negative number' => [-3, null],
    'zero' => [0, null],
    'a value of another field type' => [['url' => '/files/schema.pdf'], null],
]);

it('gives the file it holds by the path of its place, also in the items of a list', function (): void {
    $items = Items::make('bestanden', [Text::make('titel'), File::make('bestand')]);

    expect(File::make('bestand')->places(12, 'bestand'))->toBe(['bestand' => 12])
        ->and(File::make('bestand')->places('12', 'bestand'))->toBe(['bestand' => 12])
        ->and(File::make('bestand')->places(null, 'bestand'))->toBe([])
        ->and(File::make('bestand')->media(12))->toBe([12])
        ->and($items->places([['id' => 'a', 'bestand' => 4], ['id' => 'b', 'titel' => 'Twee']], 'bestanden'))->toBe(['bestanden.a.bestand' => 4]);
});

it('renders the file as its lasting link, name, extension, kind, type, size and description', function (): void {
    $files = new MediaFiles([12 => documentFile(12, alt: 'Wedstrijdschema najaar (pdf)')]);

    expect(File::make('bestand')->render(12, $files))->toBe([
        'url' => 'https://example.com/made-cms/file/01jbx3m5zq8w2p4r6t8v0x2z4b/wedstrijdschema',
        'name' => 'wedstrijdschema',
        'extension' => 'pdf',
        'kind' => 'document',
        'mime' => 'application/pdf',
        'size' => 1_240_000,
        'description' => 'Wedstrijdschema najaar (pdf)',
    ]);
});

it('renders a file without a description with none', function (): void {
    $files = new MediaFiles([12 => documentFile(12, MediaKind::Video, alt: '')]);

    expect(File::make('bestand')->render('12', $files))->toMatchArray([
        'extension' => 'mp4',
        'kind' => 'video',
        'description' => null,
    ]);
});

it('renders no file of its kinds as null', function (mixed $value, MediaFiles $files): void {
    expect(File::make('bestand')->kinds(MediaKind::Document)->render($value, $files))->toBeNull();
})->with([
    'no value' => [null, new MediaFiles([12 => documentFile(12)])],
    'a file that is gone' => [13, new MediaFiles([12 => documentFile(12)])],
    'a video for documents only' => [12, new MediaFiles([12 => documentFile(12, MediaKind::Video)])],
    'an image' => [12, new MediaFiles([12 => documentFile(12, MediaKind::Image)])],
    'a file without a folder of its own' => [12, new MediaFiles([12 => documentFile(12, key: null)])],
    'a value of another field type' => [['url' => '/files/schema.pdf'], new MediaFiles([12 => documentFile(12)])],
]);

it('renders a file as null when it is not given the files of the library', function (): void {
    expect(File::make('bestand')->render(12))->toBeNull();
});

it('renders the files in the items of a strip from the files it is given', function (): void {
    $type = new InlineStripType('downloads', [Items::make('bestanden', [Text::make('titel'), File::make('bestand')])]);
    $files = new MediaFiles([3 => documentFile(3)]);

    $rendered = $type->render(['bestanden' => [['id' => 'a', 'titel' => 'Schema', 'bestand' => 3], ['id' => 'b', 'bestand' => 6]]], $files);

    expect($rendered['bestanden'][0]['bestand']['name'] ?? null)->toBe('wedstrijdschema')
        ->and($rendered['bestanden'][1]['bestand'])->toBeNull();
});

it('has nothing to sum up', function (): void {
    expect(File::make('bestand')->summary(12))->toBeNull();
});
