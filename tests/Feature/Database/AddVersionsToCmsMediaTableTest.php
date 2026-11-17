<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const VERSION_COLUMNS = ['ulid', 'version', 'previous', 'replaced_by', 'replaced_at'];

function versionsMigration(): Migration
{
    return require __DIR__.'/../../../database/migrations/2026_11_17_000000_add_versions_to_cms_media_table.php';
}

/**
 * A file as the library stored it before cms_media had the columns of its versions.
 */
function formerMedia(string $path, ?string $originalPath = null, array $columns = []): int
{
    return DB::table('cms_media')->insertGetId([
        'kind' => 'image',
        'name' => 'teamfoto',
        'path' => $path,
        'original_path' => $originalPath,
        'mime' => 'image/webp',
        'size' => 1024,
        'created_at' => now(),
        'updated_at' => now(),
        ...$columns,
    ]);
}

it('adds the columns of the versions of a file', function (): void {
    versionsMigration()->down();
    $columns = VERSION_COLUMNS;

    expect(Schema::hasColumns('cms_media', $columns))->toBeFalse();

    versionsMigration()->up();

    $ulid = collect(Schema::getColumns('cms_media'))->firstWhere('name', 'ulid');
    $unique = collect(Schema::getIndexes('cms_media'))->first(fn (array $index): bool => $index['columns'] === ['ulid']);

    expect(Schema::hasColumns('cms_media', $columns))->toBeTrue()
        ->and($ulid['nullable'])->toBeFalse()
        ->and($unique['unique'] ?? null)->toBeTrue()
        ->and(collect(Schema::getForeignKeys('cms_media'))->firstWhere('columns', ['replaced_by'])['on_delete'] ?? null)->toBe('set null');
});

it('gives a file the folder it already has on the disk as its key, at its first version', function (): void {
    versionsMigration()->down();
    $photo = formerMedia('media/01jc8x0000000000000000000a/teamfoto.webp', 'media/01jc8x0000000000000000000a/original-abc.jpg');
    $cropped = formerMedia('media/01jc8x0000000000000000000b/k3f9a2b1/teamfoto.webp', 'media/01jc8x0000000000000000000b/original-def.jpg');
    $pdf = formerMedia('media/01JC8X0000000000000000000C/schema.pdf');

    versionsMigration()->up();
    $rows = DB::table('cms_media')->whereIn('id', [$photo, $cropped, $pdf])->orderBy('id')->get();

    expect($rows->pluck('ulid')->all())->toBe(['01jc8x0000000000000000000a', '01jc8x0000000000000000000b', '01jc8x0000000000000000000c'])
        ->and($rows->pluck('version')->map(fn (mixed $version): int => (int) $version)->all())->toBe([1, 1, 1])
        ->and($rows->pluck('previous')->filter()->all())->toBe([])
        ->and($rows->pluck('replaced_by')->filter()->all())->toBe([])
        ->and($rows->pluck('replaced_at')->filter()->all())->toBe([]);
});

it('makes up a key for a file outside a folder of its own and for a second file in the same folder', function (): void {
    versionsMigration()->down();
    $first = formerMedia('media/01jc8x0000000000000000000a/een.webp');
    $second = formerMedia('media/01jc8x0000000000000000000a/twee.webp');
    $elsewhere = formerMedia('uploads/logo.svg');

    versionsMigration()->up();
    $keys = DB::table('cms_media')->whereIn('id', [$first, $second, $elsewhere])->orderBy('id')->pluck('ulid')->all();

    expect($keys[0])->toBe('01jc8x0000000000000000000a')
        ->and($keys[1])->toMatch('/^[0-9a-z]{26}$/')->not->toBe($keys[0])
        ->and($keys[2])->toMatch('/^[0-9a-z]{26}$/')->not->toBe($keys[1]);
});

it('leaves a database that has the columns already as it is', function (): void {
    $id = formerMedia('media/01jc8x0000000000000000000a/teamfoto.webp', null, ['ulid' => '01jc8x0000000000000000000z', 'version' => 3]);

    versionsMigration()->up();

    expect(DB::table('cms_media')->where('id', $id)->value('ulid'))->toBe('01jc8x0000000000000000000z')
        ->and((int) DB::table('cms_media')->where('id', $id)->value('version'))->toBe(3);
});

it('takes the columns away again', function (): void {
    $columns = VERSION_COLUMNS;

    versionsMigration()->down();

    expect(collect($columns)->filter(fn (string $column): bool => Schema::hasColumn('cms_media', $column))->all())->toBe([]);
});
