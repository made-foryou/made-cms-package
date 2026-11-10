<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Content\Data\MediaFiles;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Strips\Fields\Image;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Tests\Fixtures\Strips\InlineStripType;

function mediaFile(int $id, MediaKind $kind = MediaKind::Image, ?string $alt = null, ?float $focalX = null, ?float $focalY = null, ?int $width = 1600, ?int $height = 1067): MediaFile
{
    return new MediaFile(
        id: $id,
        kind: $kind,
        url: "https://example.com/storage/media/{$id}/teamfoto.webp",
        name: 'teamfoto',
        mime: 'image/webp',
        size: 412_000,
        width: $width,
        height: $height,
        alt: $alt,
        focalX: $focalX,
        focalY: $focalY,
        folder: null,
    );
}

it('is an image the side panel knows by its type', function (): void {
    $image = Image::make('foto')->label('Achtergrondfoto')->hint('Minimaal 1600 px breed.')->required();

    expect($image->type())->toBe('image')
        ->and($image->describe())->toBe([
            'key' => 'foto',
            'type' => 'image',
            'label' => 'Achtergrondfoto',
            'hint' => 'Minimaal 1600 px breed.',
            'required' => true,
            'default' => null,
        ]);
});

it('validates the id of a file as present or empty, and then as an image of the library', function (): void {
    $rules = Image::make('foto')->rules('foto');
    $required = Image::make('foto')->required()->rules('foto');

    expect(array_keys($rules))->toBe(['foto'])
        ->and($rules['foto'][0])->toBe('nullable')
        ->and($rules['foto'][1])->toBeInstanceOf(Closure::class)
        ->and($required['foto'][0])->toBe('required');
});

it('stores the id of a file as a number, and anything else as no image', function (mixed $value, ?int $stored): void {
    expect(Image::make('foto')->normalize($value))->toBe($stored);
})->with([
    'a number' => [12, 12],
    'a number as text' => ['12', 12],
    'nothing' => [null, null],
    'an empty text' => ['', null],
    'a text' => ['teamfoto', null],
    'a negative number' => [-3, null],
    'zero' => [0, null],
    'a value of another field type' => [['url' => '/photos/hero.jpg', 'alt' => ''], null],
]);

it('gives the id of the file it holds', function (): void {
    expect(Image::make('foto')->media(12))->toBe([12])
        ->and(Image::make('foto')->media('12'))->toBe([12])
        ->and(Image::make('foto')->media(null))->toBe([])
        ->and(Image::make('foto')->media('teamfoto'))->toBe([]);
});

it('gives the files of the images in every item of a list, and nothing for fields without a file', function (): void {
    $items = Items::make('kaarten', [Text::make('titel'), Image::make('icoon')]);

    expect($items->media([['titel' => 'Een', 'icoon' => 4], ['titel' => 'Twee'], ['icoon' => '9'], 'geen item']))->toBe([4, 9])
        ->and($items->media(null))->toBe([])
        ->and(Text::make('titel')->media('12'))->toBe([]);
});

it('gives the files of a strip once, in the order of its fields', function (): void {
    $type = new InlineStripType('hero', [
        Image::make('foto'),
        Items::make('kaarten', [Image::make('icoon')]),
        Image::make('logo'),
    ]);

    expect($type->media(['foto' => 3, 'kaarten' => [['icoon' => 5], ['icoon' => 3]], 'logo' => null, 'oud' => 7]))->toBe([3, 5]);
});

it('renders the file as its url, alt text, size and focal point', function (): void {
    $files = new MediaFiles([12 => mediaFile(12, alt: 'Het eerste team', focalX: 0.25, focalY: 0.75)]);

    expect(Image::make('foto')->render(12, $files))->toBe([
        'url' => 'https://example.com/storage/media/12/teamfoto.webp',
        'alt' => 'Het eerste team',
        'width' => 1600,
        'height' => 1067,
        'focal' => ['x' => 0.25, 'y' => 0.75],
    ]);
});

it('renders a file without an alt text with an empty one and without a focal point at the centre', function (): void {
    $files = new MediaFiles([12 => mediaFile(12, width: null, height: null)]);

    expect(Image::make('foto')->render('12', $files))->toBe([
        'url' => 'https://example.com/storage/media/12/teamfoto.webp',
        'alt' => '',
        'width' => null,
        'height' => null,
        'focal' => ['x' => 0.5, 'y' => 0.5],
    ]);
});

it('renders no image as null', function (mixed $value, MediaFiles $files): void {
    expect(Image::make('foto')->render($value, $files))->toBeNull();
})->with([
    'no value' => [null, new MediaFiles([12 => mediaFile(12)])],
    'a file that is gone' => [13, new MediaFiles([12 => mediaFile(12)])],
    'a file that is no image' => [12, new MediaFiles([12 => mediaFile(12, kind: MediaKind::Document)])],
    'a value of another field type' => [['url' => '/photos/hero.jpg'], new MediaFiles([12 => mediaFile(12)])],
]);

it('renders an image as null when it is not given the files of the library', function (): void {
    expect(Image::make('foto')->render(12))->toBeNull();
});

it('renders the images of a strip and of its items from the files it is given', function (): void {
    $type = new InlineStripType('hero', [Image::make('foto'), Items::make('kaarten', [Image::make('icoon')])]);
    $files = new MediaFiles([3 => mediaFile(3), 5 => mediaFile(5)]);

    $rendered = $type->render(['foto' => 3, 'kaarten' => [['id' => 'a', 'icoon' => 5], ['id' => 'b', 'icoon' => 6]]], $files);

    expect($rendered['foto']['url'] ?? null)->toBe('https://example.com/storage/media/3/teamfoto.webp')
        ->and($rendered['kaarten'][0]['icoon']['url'] ?? null)->toBe('https://example.com/storage/media/5/teamfoto.webp')
        ->and($rendered['kaarten'][1]['icoon'])->toBeNull();
});

it('has nothing to sum up', function (): void {
    expect(Image::make('foto')->summary(12))->toBeNull();
});
