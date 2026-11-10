<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Enums\MediaKind;

it('stores the kind as a string value', function (MediaKind $kind, string $value): void {
    expect($kind->value)->toBe($value);
})->with([
    [MediaKind::Image, 'image'],
    [MediaKind::Document, 'document'],
    [MediaKind::Video, 'video'],
]);

it('derives the kind from the type of the contents', function (string $mime, MediaKind $kind): void {
    expect(MediaKind::fromMime($mime))->toBe($kind);
})->with([
    'a photo' => ['image/jpeg', MediaKind::Image],
    'a drawing' => ['image/svg+xml', MediaKind::Image],
    'a film' => ['video/mp4', MediaKind::Video],
    'a pdf' => ['application/pdf', MediaKind::Document],
    'any other file' => ['text/plain', MediaKind::Document],
    'capitals' => ['Image/PNG', MediaKind::Image],
]);

it('labels the kind in Dutch', function (MediaKind $kind, string $label): void {
    expect($kind->label())->toBe($label);
})->with([
    [MediaKind::Image, 'Afbeelding'],
    [MediaKind::Document, 'Document'],
    [MediaKind::Video, 'Video'],
]);

it('labels the kind in English', function (): void {
    app()->setLocale('en');

    expect(MediaKind::Image->label())->toBe('Image');
});
