<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\MediaFile;
use Made\CmsPackage\Content\Data\PageMedia;
use Made\CmsPackage\Content\Enums\MediaKind;

function pageMediaFile(int $id, MediaKind $kind): MediaFile
{
    return new MediaFile(
        id: $id,
        kind: $kind,
        url: "https://cdn.example.com/media/{$id}.webp",
        thumb: null,
        name: "bestand-{$id}",
        mime: 'image/webp',
        size: 1_000,
        width: null,
        height: null,
        alt: null,
        focalX: null,
        focalY: null,
        folder: null,
    );
}

it('counts the files of a page per kind, every kind in the order of the cases', function (): void {
    $media = new PageMedia([
        pageMediaFile(1, MediaKind::Image),
        pageMediaFile(2, MediaKind::Document),
        pageMediaFile(3, MediaKind::Image),
    ]);

    expect($media->countPerKind())->toBe(['image' => 2, 'document' => 1, 'video' => 0]);
});

it('counts nothing for a page without files', function (): void {
    expect((new PageMedia)->countPerKind())->toBe(['image' => 0, 'document' => 0, 'video' => 0])
        ->and((new PageMedia)->available)->toBeTrue();
});
