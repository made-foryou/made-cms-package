<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Enums\ShareImageRefusal;

it('takes a photo as the image of a shared link', function (string $mime): void {
    expect(ShareImageRefusal::of(MediaKind::Image, $mime))->toBeNull();
})->with(['image/webp', 'image/jpeg', 'image/png', 'image/gif']);

it('refuses what apps never show with a shared link', function (?MediaKind $kind, ?string $mime, ShareImageRefusal $refusal): void {
    expect(ShareImageRefusal::of($kind, $mime))->toBe($refusal);
})->with([
    'a file that is gone' => [null, null, ShareImageRefusal::Gone],
    'a document' => [MediaKind::Document, 'application/pdf', ShareImageRefusal::NotAnImage],
    'a video' => [MediaKind::Video, 'video/mp4', ShareImageRefusal::NotAnImage],
    'a drawing' => [MediaKind::Image, 'image/svg+xml', ShareImageRefusal::Drawing],
    'a drawing in capitals' => [MediaKind::Image, 'IMAGE/SVG+XML', ShareImageRefusal::Drawing],
]);

it('stores the reason as a string value', function (): void {
    expect(array_map(static fn (ShareImageRefusal $refusal): string => $refusal->value, ShareImageRefusal::cases()))
        ->toBe(['gone', 'kind', 'svg']);
});
