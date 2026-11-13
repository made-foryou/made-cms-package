<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\MediaName;
use Made\CmsPackage\Content\Enums\MediaKind;

it('names a file after its upload, as a slug without extension', function (string $filename, string $name): void {
    expect(MediaName::fromFilename($filename, MediaKind::Document)->value)->toBe($name);
})->with([
    'capitals, spaces and underscores' => ['Wedstrijd Schema_Najaar.PDF', 'wedstrijd-schema-najaar'],
    'dots' => ['schema.najaar.2026.pdf', 'schema-najaar-2026'],
    'accents' => ['Ëindrapport café.pdf', 'eindrapport-cafe'],
    'a path' => ['../../geheim/schema.pdf', 'schema'],
    'a windows path' => ['C:\\Users\\menno\\schema.pdf', 'schema'],
    'no letters or digits' => ['???.pdf', 'document'],
    'a very long name' => [str_repeat('lang ', 60).'.pdf', rtrim(substr(str_repeat('lang-', 60), 0, 100), '-')],
]);

it('falls back to the kind of the file', function (MediaKind $kind): void {
    expect(MediaName::fromFilename('!!!.bin', $kind)->value)->toBe($kind->value);
})->with(MediaKind::cases());

it('makes a typed name a slug, extension and all', function (string $typed, string $name): void {
    expect(MediaName::tryFrom($typed)?->value)->toBe($name);
})->with([
    'already a slug' => ['teamfoto-merwestad-4', 'teamfoto-merwestad-4'],
    'capitals and spaces' => ['Teamfoto Merwestad 4', 'teamfoto-merwestad-4'],
    'surrounding spaces' => ['  teamfoto  ', 'teamfoto'],
    'dots' => ['teamfoto.v2', 'teamfoto-v2'],
    'accents' => ['Clubavond café', 'clubavond-cafe'],
    'slashes' => ['team/foto', 'team-foto'],
    'a very long name' => [str_repeat('lang ', 60), rtrim(substr(str_repeat('lang-', 60), 0, 100), '-')],
]);

it('gives no name for a typed text without letters or digits', function (string $typed): void {
    expect(MediaName::tryFrom($typed))->toBeNull();
})->with(['empty' => [''], 'spaces' => ['   '], 'signs' => ['!?#'], 'hyphens' => ['---']]);
