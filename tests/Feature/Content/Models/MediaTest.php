<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;

it('reads a stored file in its own types', function (): void {
    $folder = MediaFolder::factory()->create(['name' => 'Teams']);
    $editor = Editor::factory()->create();

    $media = Media::factory()->in($folder)->create([
        'uploaded_by' => $editor->id,
        'focal_x' => 0.25,
        'focal_y' => 0.75,
    ]);

    $stored = Media::query()->findOrFail($media->id);

    expect($stored->kind)->toBe(MediaKind::Image)
        ->and($stored->folder_id)->toBe($folder->id)
        ->and($stored->folder?->name)->toBe('Teams')
        ->and($stored->size)->toBeInt()
        ->and($stored->width)->toBe(1920)
        ->and($stored->height)->toBe(1280)
        ->and($stored->focal_x)->toBe(0.25)
        ->and($stored->focal_y)->toBe(0.75)
        ->and($stored->uploaded_by)->toBe($editor->id)
        ->and($folder->media()->pluck('id')->all())->toBe([$media->id]);
});

it('keeps a document without dimensions or original', function (): void {
    $media = Media::factory()->document()->create(['name' => 'wedstrijdschema']);

    $stored = Media::query()->findOrFail($media->id);

    expect($stored->kind)->toBe(MediaKind::Document)
        ->and($stored->mime)->toBe('application/pdf')
        ->and($stored->path)->toEndWith('/wedstrijdschema.pdf')
        ->and($stored->original_path)->toBeNull()
        ->and($stored->width)->toBeNull()
        ->and($stored->height)->toBeNull();
});

it('keeps a folder that still holds files', function (): void {
    $folder = MediaFolder::factory()->create();
    Media::factory()->in($folder)->create();

    $folder->delete();
})->throws(QueryException::class);

it('refuses two folders with the same name', function (): void {
    MediaFolder::factory()->create(['name' => 'Teams']);

    MediaFolder::factory()->create(['name' => 'Teams']);
})->throws(QueryException::class);

it('keeps the file of an editor that is removed, without the editor', function (): void {
    $editor = Editor::factory()->create();
    $media = Media::factory()->create(['uploaded_by' => $editor->id]);

    $editor->delete();

    expect(Media::query()->findOrFail($media->id)->uploaded_by)->toBeNull();
});

it('keeps the folder of a file and the version that waits to be put back', function (): void {
    $editor = Editor::factory()->create();
    $media = Media::factory()->replaced()->create(['replaced_by' => $editor->id]);

    $stored = Media::query()->findOrFail($media->id);

    expect($stored->ulid)->toMatch('/^[0-9a-z]{26}$/')
        ->and($stored->path)->toStartWith("media/{$stored->ulid}/")
        ->and($stored->version)->toBe(2)
        ->and($stored->replaced_by)->toBe($editor->id)
        ->and($stored->replaced_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($stored->previous)->toBeArray()
        ->and($stored->previous['version'] ?? null)->toBe(1);
});

it('gives every file a folder of its own and starts at version 1', function (): void {
    $first = Media::factory()->create();
    $second = Media::factory()->document()->create();

    expect($first->ulid)->not->toBe($second->ulid)
        ->and($second->path)->toStartWith("media/{$second->ulid}/")
        ->and(Media::query()->findOrFail($first->id)->version)->toBe(1)
        ->and($first->previous)->toBeNull();
});

it('refuses two files in the same folder', function (): void {
    $media = Media::factory()->create();

    Media::factory()->create(['ulid' => $media->ulid]);
})->throws(QueryException::class);
