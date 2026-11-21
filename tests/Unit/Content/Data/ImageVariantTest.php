<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\ImageVariant;

it('reads the stored sizes of a photo and gives them back the same', function (): void {
    $stored = [
        ['path' => 'media/01k9/480w.webp', 'width' => 480, 'height' => 320],
        ['path' => 'media/01k9/960w.webp', 'width' => 960, 'height' => 640],
    ];

    $variants = ImageVariant::listFromArray($stored);

    expect($variants)->toEqual([
        new ImageVariant('media/01k9/480w.webp', 480, 320),
        new ImageVariant('media/01k9/960w.webp', 960, 640),
    ])->and(ImageVariant::listToArray($variants))->toBe($stored);
});

it('reads an empty list as sizes that were made, and nothing as sizes that were not', function (): void {
    expect(ImageVariant::listFromArray([]))->toBe([])
        ->and(ImageVariant::listFromArray(null))->toBeNull()
        ->and(ImageVariant::listFromArray('480w.webp'))->toBeNull()
        ->and(ImageVariant::listToArray(null))->toBeNull();
});

it('leaves out what is no size of a photo', function (): void {
    expect(ImageVariant::listFromArray([
        ['path' => 'media/01k9/480w.webp', 'width' => 480, 'height' => 320],
        ['path' => 'media/01k9/960w.webp', 'width' => '960', 'height' => 640],
        ['width' => 1440, 'height' => 960],
        'media/01k9/1440w.webp',
    ]))->toEqual([new ImageVariant('media/01k9/480w.webp', 480, 320)]);
});
