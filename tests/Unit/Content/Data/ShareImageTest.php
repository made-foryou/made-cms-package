<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\ShareImage;

it('gives the image of a shared link as an array for a prop', function (): void {
    $image = new ShareImage(url: 'https://example.com/storage/deel.webp', width: 1200, height: 630, alt: 'Spelers na de winst');

    expect($image->toArray())->toBe([
        'url' => 'https://example.com/storage/deel.webp',
        'width' => 1200,
        'height' => 630,
        'alt' => 'Spelers na de winst',
    ]);
});

it('gives an image without a size or an alt text with nulls', function (): void {
    expect((new ShareImage('https://example.com/deel.jpg'))->toArray())->toBe([
        'url' => 'https://example.com/deel.jpg',
        'width' => null,
        'height' => null,
        'alt' => null,
    ]);
});
