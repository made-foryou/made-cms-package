<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Content\Data\MediaVersion;

function storedVersion(array $overrides = []): array
{
    return [
        'version' => 1,
        'name' => 'teamfoto',
        'path' => 'media/01jc8x0000000000000000000a/k3f9a2b1/teamfoto.webp',
        'original_path' => 'media/01jc8x0000000000000000000a/original-abc.jpg',
        'thumb_path' => 'media/01jc8x0000000000000000000a/thumb-abc.webp',
        'mime' => 'image/webp',
        'size' => 412_000,
        'width' => 1920,
        'height' => 1280,
        'original_width' => 4032,
        'original_height' => 3024,
        'focal_x' => 0.25,
        'focal_y' => 0.75,
        'crop' => ['x' => 10, 'y' => 20, 'width' => 400, 'height' => 300, 'rotation' => 0, 'flip' => false, 'scale_down' => true],
        'placed_by' => 7,
        'placed_at' => '2026-10-20T14:30:00+00:00',
        ...$overrides,
    ];
}

it('reads a stored version and gives it back the same', function (): void {
    $version = MediaVersion::fromArray(storedVersion());

    expect($version)->toBeInstanceOf(MediaVersion::class)
        ->and($version?->version)->toBe(1)
        ->and($version?->name)->toBe('teamfoto')
        ->and($version?->originalPath)->toBe('media/01jc8x0000000000000000000a/original-abc.jpg')
        ->and($version?->focalX)->toBe(0.25)
        ->and($version?->placedBy)->toBe(7)
        ->and($version?->placedAt?->equalTo(CarbonImmutable::parse('2026-10-20T14:30:00+00:00')))->toBeTrue()
        ->and($version?->toArray())->toBe(storedVersion());
});

it('reads a version kept as it came in, without original, thumbnail, size or crop', function (): void {
    $stored = storedVersion([
        'path' => 'media/01jc8x0000000000000000000a/schema.pdf',
        'original_path' => null,
        'thumb_path' => null,
        'mime' => 'application/pdf',
        'width' => null,
        'height' => null,
        'original_width' => null,
        'original_height' => null,
        'focal_x' => null,
        'focal_y' => null,
        'crop' => null,
        'placed_by' => null,
        'placed_at' => null,
    ]);

    $version = MediaVersion::fromArray($stored);

    expect($version?->toArray())->toBe($stored)
        ->and($version?->files())->toBe(['media/01jc8x0000000000000000000a/schema.pdf'])
        ->and($version?->home())->toBe('media/01jc8x0000000000000000000a');
});

it('reads nothing from a value that is no version', function (mixed $value): void {
    expect(MediaVersion::fromArray($value))->toBeNull();
})->with([
    'null' => [null],
    'a text' => ['teamfoto'],
    'without a path' => [storedVersion(['path' => null])],
    'without a version' => [storedVersion(['version' => '1'])],
    'a size that is no number' => [storedVersion(['size' => '412000'])],
    'a date that is none' => [storedVersion(['placed_at' => 'gisteren'])],
]);

it('lists its files and the folder they are in', function (): void {
    $version = MediaVersion::fromArray(storedVersion());

    expect($version?->files())->toBe([
        'media/01jc8x0000000000000000000a/k3f9a2b1/teamfoto.webp',
        'media/01jc8x0000000000000000000a/thumb-abc.webp',
        'media/01jc8x0000000000000000000a/original-abc.jpg',
    ])->and($version?->home())->toBe('media/01jc8x0000000000000000000a');
});

it('gives the columns that put it back on the row', function (): void {
    $columns = MediaVersion::fromArray(storedVersion())?->columns();

    expect($columns)->toMatchArray([
        'version' => 1,
        'name' => 'teamfoto',
        'path' => 'media/01jc8x0000000000000000000a/k3f9a2b1/teamfoto.webp',
        'original_path' => 'media/01jc8x0000000000000000000a/original-abc.jpg',
        'thumb_path' => 'media/01jc8x0000000000000000000a/thumb-abc.webp',
        'mime' => 'image/webp',
        'size' => 412_000,
        'focal_x' => 0.25,
        'crop' => storedVersion()['crop'],
        'replaced_by' => 7,
    ])->and($columns['replaced_at'] ?? null)->toBeInstanceOf(CarbonImmutable::class);
});
